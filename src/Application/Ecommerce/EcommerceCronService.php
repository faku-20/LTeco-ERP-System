<?php
declare(strict_types=1);

final class EcommerceCronService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function resumen(): array
    {
        return [
            'notificaciones' => (int) $this->pdo->query("SELECT COUNT(*) FROM ecommerce_notificacion WHERE Estado IN ('Pendiente','Fallida') AND Intentos < 5")->fetchColumn(),
            'reservas_vencidas' => (int) $this->pdo->query("SELECT COUNT(*) FROM ecommerce_pedido WHERE Estado = 'PendientePago' AND ExpiraEn < NOW()")->fetchColumn(),
            'services_proximos' => (int) $this->pdo->query("SELECT COUNT(*) FROM service_vehiculo sv JOIN ecommerce_cuenta c ON c.IdCliente = sv.IdCliente AND c.Estado = 'Activa' WHERE sv.Estado = 'Pendiente' AND sv.FechaProgramada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)")->fetchColumn(),
        ];
    }

    public function encolarRecordatorios(): void
    {
        $this->pdo->exec("INSERT IGNORE INTO ecommerce_notificacion (IdCuenta,IdService,Tipo,Destinatario)
SELECT c.IdCuenta, sv.IdService, 'RecordatorioService', c.Correo FROM service_vehiculo sv
JOIN ecommerce_cuenta c ON c.IdCliente = sv.IdCliente AND c.Estado = 'Activa'
WHERE sv.Estado = 'Pendiente' AND sv.FechaProgramada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)");
    }

    public function liberarReservasVencidas(): void
    {
        $this->pdo->beginTransaction();
        try {
            $ids = $this->pdo->query("SELECT IdPedido FROM ecommerce_pedido WHERE Estado = 'PendientePago' AND ExpiraEn < NOW() FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) {
                $stmt = $this->pdo->prepare('SELECT IdVehiculo,IdProducto FROM ecommerce_pedido_item WHERE IdPedido=?');
                $stmt->execute([(int) $id]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                    $this->pdo->prepare("DELETE FROM ecommerce_ocupacion_unidad WHERE IdPedido=? AND Estado='Reservada'")->execute([(int) $id]);
                    $this->pdo->prepare('UPDATE vehiculo SET ClienteReservaId=NULL,FechaReserva=NULL,SeniaReserva=NULL WHERE IdVehiculo=? AND FechaVenta IS NULL')->execute([$item['IdVehiculo']]);
                    $this->pdo->prepare("UPDATE producto SET Estado='Disponible',Stock=1 WHERE IdProducto=? AND Estado='Reservado'")->execute([(int) $item['IdProducto']]);
                }
                $this->pdo->prepare("UPDATE ecommerce_pedido SET Estado='Vencido',EstadoPago='Cancelado',VersionBloqueo=VersionBloqueo+1 WHERE IdPedido=?")->execute([(int) $id]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function procesarNotificaciones(callable $enviar, callable $url): array
    {
        $stmt = $this->pdo->query("SELECT n.*,p.NumeroPedido,p.StorefrontOrderUuid,p.IdVenta,p.EstadoPago,p.Nombre,p.Apellido,p.Telefono,p.ProveedorPago,p.Total,p.Moneda,p.ExpiraEn,(SELECT GROUP_CONCAT(CONCAT(i.Modelo,IF(i.CapacidadBateriaAh IS NULL,'',CONCAT(' ',i.CapacidadBateriaAh,'Ah')),IF(i.Color IS NULL OR i.Color='','',CONCAT(' ',i.Color))) ORDER BY i.IdItem SEPARATOR ', ') FROM ecommerce_pedido_item i WHERE i.IdPedido=p.IdPedido) AS ItemsResumen,sv.NumeroService,sv.FechaProgramada,v.Modelo FROM ecommerce_notificacion n LEFT JOIN ecommerce_pedido p ON p.IdPedido=n.IdPedido LEFT JOIN service_vehiculo sv ON sv.IdService=n.IdService LEFT JOIN vehiculo v ON v.IdVehiculo=sv.IdVehiculo WHERE n.Estado IN ('Pendiente','Fallida') AND n.Intentos < 5 ORDER BY n.IdNotificacion LIMIT 50");
        $enviadas = 0;
        $fallidas = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
            if ($this->debeOmitirNotificacion((array) $n)) {
                $this->omitir((int) $n['IdNotificacion'], 'Omitida: el pedido ya tiene pago confirmado.');
                $enviadas++;
                continue;
            }
            $p = \Lteco\Application\Ecommerce\EcommerceNotificationTemplate::build($n);
            if (!$p) {
                $this->fallar((int) $n['IdNotificacion'], 'Tipo no soportado');
                $fallidas++;
                continue;
            }
            $ok = $enviar((string) $n['Destinatario'], $p['subject'], $p['title'], $p['message'], $p['button'], $url($n));
            if ($ok) {
                $this->pdo->prepare("UPDATE ecommerce_notificacion SET Estado='Enviada',Intentos=Intentos+1,EnviadaEn=NOW(),UltimoError=NULL WHERE IdNotificacion=?")->execute([(int) $n['IdNotificacion']]);
                $enviadas++;
            } else {
                $this->fallar((int) $n['IdNotificacion'], 'SMTP no aceptó el mensaje');
                $fallidas++;
            }
        }
        return compact('enviadas', 'fallidas');
    }

    public function marcarConciliaciones(): void
    {
        $this->pdo->exec("INSERT IGNORE INTO ecommerce_evento (IdPedido,IdCuenta,Tipo,ClaveIdempotencia,PayloadJson)
SELECT p.IdPedido,p.IdCuenta,'ConciliacionRequerida',CONCAT('conciliar-',p.IdPedido),JSON_OBJECT('estado',p.Estado,'pago',p.EstadoPago) FROM ecommerce_pedido p
WHERE (p.EstadoPago='Aprobado' AND p.IdVenta IS NULL AND p.Estado<>'ExcepcionPagoSinStock') OR (p.IdVenta IS NOT NULL AND p.EstadoPago<>'Aprobado' AND p.Estado NOT IN ('ReembolsoPendiente','Reembolsado'))");
    }

    /** @param list<string> $telefonos @return array{enviados:int,fallidos:int,omitidos:int} */
    public function procesarWhatsAppPedidosWeb(array $telefonos, bool $configurado, string $panelBaseUrl, callable $enviar): array
    {
        $telefonos = array_values(array_unique(array_filter(array_map('trim', $telefonos))));
        if ($telefonos === []) {
            return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => 0];
        }
        $lock = (int) $this->pdo->query("SELECT GET_LOCK('ltecobike:pedido_web_whatsapp',0)")->fetchColumn();
        if ($lock !== 1) {
            return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => 0];
        }

        try {
            $where = "FROM internal_alert a JOIN ecommerce_pedido p ON p.IdPedido=a.SourceId WHERE a.Tipo='pedido_web_nuevo' AND a.SourceType='ecommerce_pedido' AND p.Estado NOT IN ('Cancelado','Vencido') AND NOT EXISTS (SELECT 1 FROM notificacion_whatsapp w WHERE w.Tipo='venta' AND w.IdReferencia=p.IdPedido AND w.Template='pedido_web_interno' AND w.Estado='enviado')";
            if (!$configurado) {
                $pendientes = (int) $this->pdo->query("SELECT COUNT(*) {$where}")->fetchColumn();
                return ['enviados' => 0, 'fallidos' => 0, 'omitidos' => $pendientes * count($telefonos)];
            }

            $stmt = $this->pdo->query("SELECT a.SourceId AS IdPedido,p.NumeroPedido,p.Nombre,p.Apellido,p.Telefono,p.ProveedorPago,p.Moneda,p.Total,p.ExpiraEn,(SELECT GROUP_CONCAT(CONCAT(i.Modelo,IF(i.CapacidadBateriaAh IS NULL,'',CONCAT(' ',i.CapacidadBateriaAh,'Ah')),IF(i.Color IS NULL OR i.Color='','',CONCAT(' ',i.Color))) ORDER BY i.IdItem SEPARATOR ', ') FROM ecommerce_pedido_item i WHERE i.IdPedido=p.IdPedido) AS ItemsResumen {$where} ORDER BY a.IdAlert ASC LIMIT 20");
            $enviados = 0;
            $fallidos = 0;
            $omitidos = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $pedido) {
                $link = rtrim($panelBaseUrl, '/') . '/lteco-panel/ecommerce/ver.php?id=' . (int) $pedido['IdPedido'];
                $isCash = (string) ($pedido['ProveedorPago'] ?? '') === 'cash';
                $paymentLabel = $isCash ? 'efectivo coordinado' : 'tarjeta';
                $followup = $isCash
                    ? "Reserva hasta: " . (!empty($pedido['ExpiraEn']) ? date('d/m/Y H:i', strtotime((string) $pedido['ExpiraEn'])) : 'a confirmar') . "\n"
                    : "Estado: venta confirmada con tarjeta\n";
                $mensaje = ($isCash ? "Nueva reserva web\n" : "Nueva venta web con tarjeta\n")
                    . "Pedido: " . (string) $pedido['NumeroPedido'] . "\n"
                    . "Cliente: " . trim((string) $pedido['Nombre'] . ' ' . (string) $pedido['Apellido']) . "\n"
                    . "Tel: " . (string) $pedido['Telefono'] . "\n"
                    . "Unidad: " . ((string) ($pedido['ItemsResumen'] ?? '') ?: 'A confirmar') . "\n"
                    . "Pago: " . $paymentLabel . "\n"
                    . "Total: " . (string) $pedido['Moneda'] . ' ' . number_format((float) $pedido['Total'], 2, ',', '.') . "\n"
                    . $followup
                    . "Panel: " . $link;
                foreach ($telefonos as $telefono) {
                    $ok = $enviar($telefono, $mensaje, (int) $pedido['IdPedido']);
                    if ($ok) {
                        $enviados++;
                    } else {
                        $fallidos++;
                    }
                }
            }
            return ['enviados' => $enviados, 'fallidos' => $fallidos, 'omitidos' => $omitidos];
        } finally {
            $this->pdo->query("DO RELEASE_LOCK('ltecobike:pedido_web_whatsapp')");
        }
    }

    private function fallar(int $id, string $error): void
    {
        $this->pdo->prepare("UPDATE ecommerce_notificacion SET Estado='Fallida',Intentos=Intentos+1,UltimoError=? WHERE IdNotificacion=?")->execute([$error, $id]);
    }

    private function omitir(int $id, string $motivo): void
    {
        $this->pdo->prepare("UPDATE ecommerce_notificacion SET Estado='Enviada',Intentos=Intentos+1,EnviadaEn=NOW(),UltimoError=? WHERE IdNotificacion=?")->execute([$motivo, $id]);
    }

    private function debeOmitirNotificacion(array $notificacion): bool
    {
        return (string) ($notificacion['Tipo'] ?? '') === 'ReservaCreada'
            && ((int) ($notificacion['IdVenta'] ?? 0) > 0 || (string) ($notificacion['EstadoPago'] ?? '') === 'Aprobado');
    }
}
