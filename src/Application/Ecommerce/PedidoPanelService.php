<?php
declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

use Lteco\Domain\Venta\ConfiguracionComercial;
use Lteco\Domain\Venta\ReglasComerciales;
use Lteco\Infrastructure\Db\Connection;

final class PedidoPanelService
{
    public function __construct(private Connection $connection)
    {
    }

    public function actualizarEstado(int $id, string $estado, int $idUsuario, ?string $receptor = null, ?string $evidencia = null): void
    {
        if ($id <= 0 || !in_array($estado, ['Preparando', 'Listo', 'Entregado', 'Cancelado'], true)) {
            throw new \RuntimeException('Estado no válido.');
        }

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $lockedReservationId = '';
            if ($estado === 'Cancelado') {
                $pre = $pdo->prepare('SELECT StorefrontReservationId FROM ecommerce_pedido WHERE IdPedido=?');
                $pre->execute([$id]);
                $lockedReservationId = trim((string) ($pre->fetchColumn() ?: ''));
                if ($lockedReservationId !== '') {
                    $lockReservation = $pdo->prepare('SELECT ReservationId FROM storefront_reservation WHERE ReservationId=? FOR UPDATE');
                    $lockReservation->execute([$lockedReservationId]);
                }
            }

            $stmt = $pdo->prepare('SELECT Estado,EstadoPago,IdVenta,IdCliente,IdCuenta,Correo,EntregadoEn,StorefrontReservationId FROM ecommerce_pedido WHERE IdPedido=? FOR UPDATE');
            $stmt->execute([$id]);
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$pedido) {
                throw new \RuntimeException('Pedido inexistente.');
            }
            if ($estado === 'Cancelado' && trim((string) ($pedido['StorefrontReservationId'] ?? '')) !== $lockedReservationId) {
                throw new \RuntimeException('La reserva del pedido cambió durante la operación. Intentá nuevamente.');
            }

            $estadoPago = (string) $pedido['EstadoPago'];
            $anterior = (string) $pedido['Estado'];
            $transiciones = [
                'Pagado' => ['Preparando', 'Cancelado'],
                'Preparando' => ['Listo', 'Cancelado'],
                'Listo' => ['Entregado', 'Cancelado'],
                'PendientePago' => ['Cancelado'],
                'PagoEnRevision' => ['Cancelado'],
            ];
            if (!in_array($estado, $transiciones[$anterior] ?? [], true)) {
                throw new \RuntimeException("No se puede pasar de {$anterior} a {$estado}.");
            }
            if ($estado === 'Cancelado' && $estadoPago === 'Aprobado') {
                throw new \RuntimeException('Un pedido pagado requiere anular o reembolsar la venta antes de cancelarlo.');
            }

            if ($estado === 'Cancelado') {
                $stmt = $pdo->prepare('SELECT IdVehiculo,IdProducto FROM ecommerce_pedido_item WHERE IdPedido=?');
                $stmt->execute([$id]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $item) {
                    $pdo->prepare('UPDATE vehiculo SET ClienteReservaId=NULL,FechaReserva=NULL,SeniaReserva=NULL WHERE IdVehiculo=? AND FechaVenta IS NULL')->execute([$item['IdVehiculo']]);
                    $pdo->prepare("UPDATE producto SET Estado='Disponible',Stock=1 WHERE IdProducto=? AND Estado='Reservado'")->execute([(int) $item['IdProducto']]);
                }
                $reservationId = trim((string) ($pedido['StorefrontReservationId'] ?? ''));
                if ($reservationId !== '') {
                    $pdo->prepare("UPDATE storefront_reservation SET Estado='released',LiberadaEn=NOW() WHERE ReservationId=? AND Estado='active'")
                        ->execute([$reservationId]);
                }
            }

            if ($estado === 'Entregado') {
                $receptor = trim((string) $receptor);
                if ($receptor === '') {
                    throw new \RuntimeException('Indicá quién recibió la moto.');
                }
                if ((int) $pedido['IdVenta'] <= 0 || (int) $pedido['IdCliente'] <= 0) {
                    throw new \RuntimeException('El pedido no tiene venta o cliente asociado.');
                }

                $stmt = $pdo->prepare('SELECT IdVehiculo FROM ecommerce_pedido_item WHERE IdPedido=? ORDER BY IdItem');
                $stmt->execute([$id]);
                $vehiculos = array_values(array_filter(array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN))));
                if ($vehiculos === []) {
                    throw new \RuntimeException('El pedido no tiene unidades asociadas.');
                }

                $lineas = new \Lteco\Application\Venta\VentaLineasService(new Connection($pdo));
                foreach ($vehiculos as $idVehiculo) {
                    $lineas->activarPostventaEnEntrega($idVehiculo, (int) $pedido['IdVenta'], (int) $pedido['IdCliente'], date('Y-m-d'));
                }

                $pdo->prepare('UPDATE ecommerce_pedido SET Estado=?,EntregadoEn=NOW(),EntregadoPor=?,ReceptorEntrega=?,EvidenciaEntrega=?,VersionBloqueo=VersionBloqueo+1 WHERE IdPedido=?')
                    ->execute([$estado, $idUsuario, $receptor, trim((string) $evidencia) ?: null, $id]);
            } else {
                $pdo->prepare('UPDATE ecommerce_pedido SET Estado=?,EstadoPago=IF(?="Cancelado","Cancelado",EstadoPago),VersionBloqueo=VersionBloqueo+1 WHERE IdPedido=?')->execute([$estado, $estado, $id]);
            }

            $pdo->prepare('INSERT INTO ecommerce_auditoria (IdPedido,IdUsuario,Accion,EstadoAnterior,EstadoNuevo,MetadataJson) VALUES (?,?,?,?,?,?)')
                ->execute([$id, $idUsuario, 'CambioEstadoPanel', $anterior, $estado, json_encode(['receptor' => $receptor, 'evidencia' => $evidencia], JSON_UNESCAPED_UNICODE)]);

            $tipoNotificacion = ['Preparando' => 'PedidoPreparando', 'Listo' => 'PedidoListo', 'Entregado' => 'PedidoEntregado', 'Cancelado' => 'PedidoCancelado'][$estado] ?? null;
            if ($tipoNotificacion) {
                $pdo->prepare('INSERT IGNORE INTO ecommerce_notificacion (IdCuenta,IdPedido,Tipo,Destinatario) VALUES (?,?,?,?)')->execute([(int) $pedido['IdCuenta'] ?: null, $id, $tipoNotificacion, (string) $pedido['Correo']]);
            }

            $this->encolarAlerta($pdo, $id, 'pedido_web_' . strtolower($estado), $estado === 'Cancelado' ? 'warning' : 'info', 'Pedido web ' . $estado, "El pedido #{$id} cambió de {$anterior} a {$estado}.", $idUsuario);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function confirmarPagoEfectivo(int $id, int $idUsuario): int
    {
        return $this->confirmarPago($id, 'cash', 'Efectivo', 'cash', 'PagoEfectivoConfirmado', 'Venta web en efectivo ', 'CASH-', 'cash-confirmed-', $idUsuario);
    }

    public function confirmarPagoTarjetaSimulada(string $orderUuid): int
    {
        $orderUuid = strtolower(trim($orderUuid));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $orderUuid) !== 1) {
            throw new \RuntimeException('Pedido inexistente.');
        }

        $stmt = $this->connection->pdo()->prepare('SELECT IdPedido FROM ecommerce_pedido WHERE StorefrontOrderUuid=? LIMIT 1');
        $stmt->execute([$orderUuid]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id <= 0) {
            throw new \RuntimeException('Pedido inexistente.');
        }

        return $this->confirmarPago($id, 'card', 'Tarjeta', 'card_simulator', 'PagoTarjetaSimuladaConfirmado', 'Venta web con tarjeta simulada ', 'SIM-CARD-', 'simulated-card-approved-', null);
    }

    private function confirmarPago(
        int $id,
        string $proveedorEsperado,
        string $metodoVenta,
        string $proveedorPago,
        string $accionAuditoria,
        string $observacionPrefijo,
        string $referenciaPrefijo,
        string $eventoPrefijo,
        ?int $idUsuario,
    ): int {
        if ($id <= 0) {
            throw new \RuntimeException('Pedido inexistente.');
        }

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $pre = $pdo->prepare('SELECT StorefrontReservationId FROM ecommerce_pedido WHERE IdPedido=?');
            $pre->execute([$id]);
            $reservationId = trim((string) ($pre->fetchColumn() ?: ''));
            if ($reservationId === '') {
                throw new \RuntimeException('El pedido no tiene una reserva asociada.');
            }

            $lockReservation = $pdo->prepare('SELECT Estado FROM storefront_reservation WHERE ReservationId=? FOR UPDATE');
            $lockReservation->execute([$reservationId]);
            $reservationState = $lockReservation->fetchColumn();
            if ($reservationState === false) {
                throw new \RuntimeException('La reserva del pedido no existe.');
            }

            $stmt = $pdo->prepare('SELECT * FROM ecommerce_pedido WHERE IdPedido=? FOR UPDATE');
            $stmt->execute([$id]);
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$pedido) {
                throw new \RuntimeException('Pedido inexistente.');
            }
            if (!hash_equals($reservationId, (string) ($pedido['StorefrontReservationId'] ?? ''))) {
                throw new \RuntimeException('La reserva del pedido cambió durante la operación. Intentá nuevamente.');
            }
            if ((int) ($pedido['IdVenta'] ?? 0) > 0) {
                $pdo->commit();
                return (int) $pedido['IdVenta'];
            }
            if ($pedido['ProveedorPago'] !== $proveedorEsperado || $pedido['Estado'] !== 'PagoEnRevision' || $pedido['EstadoPago'] !== 'Pendiente') {
                throw new \RuntimeException('El pedido no está esperando este medio de pago.');
            }
            if (strtotime((string) $pedido['ExpiraEn']) < time()) {
                throw new \RuntimeException('La reserva venció; no confirmes el pago sin reservar otra unidad.');
            }

            $stmt = $pdo->prepare("SELECT i.*,p.Costo,p.GastoTotal,p.Estado ProductoEstado,v.FechaVenta FROM ecommerce_pedido_item i STRAIGHT_JOIN producto p ON p.IdProducto=i.IdProducto STRAIGHT_JOIN vehiculo v ON v.IdVehiculo=i.IdVehiculo WHERE i.IdPedido=? ORDER BY i.IdVehiculo FOR UPDATE");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!$items) {
                throw new \RuntimeException('El pedido no tiene unidades.');
            }
            foreach ($items as $item) {
                if ($reservationState !== 'active' || !empty($item['FechaVenta']) || $item['ProductoEstado'] !== 'Reservado') {
                    throw new \RuntimeException('La unidad ya no está reservada para este pedido.');
                }
            }

            $clienteId = $this->resolverClientePedido($pdo, $pedido);
            $costoTotal = array_sum(array_map(static fn (array $i): float => (float) $i['Costo'] + (float) $i['GastoTotal'], $items));
            $total = (float) $pedido['Total'];
            $tasaIVA = $this->tasaIvaPedido($pdo, $id);
            $calculo = ReglasComerciales::calcular([
                'subtotalBruto' => $total,
                'metodoPago' => $metodoVenta,
                'tipoCliente' => 'Final',
                'costoTotal' => $costoTotal,
                'tasaIVA' => $tasaIVA,
            ]);
            $ganancia = (float) $calculo['ganancia'];
            $iva = (float) $calculo['montoIVA'];
            $totalSinIVA = (float) $calculo['totalSinIVA'];

            $stmt = $pdo->prepare("INSERT INTO venta (Cliente_IdCliente,MetodoPago,EstadoVenta,TipoCliente,Total,MontoPagado,SaldoPendiente,GananciaEstimada,SubtotalBruto,MontoIVA,TotalSinIVA,Observaciones,Moneda,NumeroFactura) VALUES (?,?, 'Confirmada','Final',?,?,0,?,?,?,?,?,?,?)");
            $stmt->execute([$clienteId, $metodoVenta, $total, $total, $ganancia, $total, $iva, $totalSinIVA, $observacionPrefijo . $pedido['NumeroPedido'], $pedido['Moneda'], $pedido['NumeroPedido']]);
            $idVenta = (int) $pdo->lastInsertId();

            $grossTotal = array_sum(array_map(static fn (array $i): float => (float) $i['Total'], $items));
            $lineas = new \Lteco\Application\Venta\VentaLineasService(new Connection($pdo));
            foreach ($items as $index => $item) {
                $lineTotal = $index === array_key_last($items)
                    ? $total - array_sum(array_map(static fn (array $x): float => (float) ($x['_net'] ?? 0), array_slice($items, 0, $index)))
                    : round($total * ((float) $item['Total'] / $grossTotal), 2);
                $items[$index]['_net'] = $lineTotal;
                $costo = (float) $item['Costo'] + (float) $item['GastoTotal'];
                $lineas->registrarVehiculoPendienteEntrega([
                    'idVenta' => $idVenta,
                    'idProducto' => (int) $item['IdProducto'],
                    'precioUnitario' => $lineTotal,
                    'costoUnitario' => $costo,
                    'subtotal' => $lineTotal,
                    'gananciaLinea' => $lineTotal - $costo,
                    'moneda' => $pedido['Moneda'],
                    'idVehiculo' => $item['IdVehiculo'],
                    'clienteId' => $clienteId,
                ]);
            }

            $pdo->prepare("UPDATE storefront_reservation SET Estado='consumed' WHERE ReservationId=? AND Estado='active'")->execute([(string) $pedido['StorefrontReservationId']]);
            $pdo->prepare("INSERT INTO ecommerce_pago (IdPedido,Proveedor,Tipo,IdExterno,IdEventoExterno,Estado,Monto,Moneda,PayloadJson) VALUES (?,?, 'Cobro',?,?,'approved',?,?,?)")->execute([$id, $proveedorPago, $referenciaPrefijo . $id, $eventoPrefijo . $id, $total, $pedido['Moneda'], json_encode(['confirmed_by' => $idUsuario, 'simulated' => $proveedorPago === 'card_simulator'], JSON_THROW_ON_ERROR)]);
            $pdo->prepare("UPDATE ecommerce_pedido SET IdCliente=?,Estado='Pagado',EstadoPago='Aprobado',PagadoEn=NOW(),IdVenta=?,VersionBloqueo=VersionBloqueo+1 WHERE IdPedido=?")->execute([$clienteId, $idVenta, $id]);
            $pdo->prepare("INSERT INTO ecommerce_auditoria (IdPedido,IdUsuario,Accion,EstadoAnterior,EstadoNuevo,MetadataJson) VALUES (?,?,?,'PagoEnRevision','Pagado',?)")->execute([$id, $idUsuario, $accionAuditoria, json_encode(['id_venta' => $idVenta, 'simulated' => $proveedorPago === 'card_simulator'], JSON_THROW_ON_ERROR)]);
            $pdo->prepare("INSERT IGNORE INTO ecommerce_notificacion (IdCuenta,IdPedido,Tipo,Destinatario) VALUES (NULL,?,'PagoConfirmado',?)")->execute([$id, $pedido['Correo']]);
            $this->encolarAlerta($pdo, $id, 'pedido_web_pago', 'info', 'Pago web confirmado', 'Se confirmó el pago de ' . $pedido['NumeroPedido'] . ' y se creó la venta #' . $idVenta . '.', $idUsuario);
            $pdo->commit();
            $this->enviarWhatsappConfirmacionVentaWeb($idVenta, $clienteId, (string) $pedido['NumeroPedido']);
            return $idVenta;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function enviarWhatsappConfirmacionVentaWeb(int $idVenta, int $clienteId, string $numeroComprobante): void
    {
        if ($idVenta <= 0 || $clienteId <= 0) {
            return;
        }

        try {
            $whatsappPath = dirname(__DIR__, 3) . '/lteco-panel/includes/whatsapp.php';
            if (!is_file($whatsappPath)) {
                return;
            }
            require_once $whatsappPath;
            $pdo = $this->connection->pdo();
            $cfg = \whatsappObtenerConfig($pdo);
            if (empty($cfg['enabled'])) {
                return;
            }

            $templatesIdempotencia = ['compra_confirmada_cliente'];
            if (!empty($cfg['tpl_venta'])) {
                $templatesIdempotencia[] = (string) $cfg['tpl_venta'];
            }
            $placeholders = implode(',', array_fill(0, count($templatesIdempotencia), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacion_whatsapp WHERE Tipo='venta' AND IdReferencia=? AND Template IN ({$placeholders}) AND Estado='enviado' AND COALESCE(EstadoEntrega,'') <> 'failed'");
            $stmt->execute(array_merge([$idVenta], $templatesIdempotencia));
            if ((int) $stmt->fetchColumn() > 0) {
                return;
            }

            $lineas = new \Lteco\Application\Venta\VentaLineasService(new Connection($pdo));
            $cliente = $lineas->clienteWhatsapp($clienteId);
            if (!$cliente || empty($cliente['Telefono'])) {
                return;
            }

            $comprobante = trim($numeroComprobante) !== '' ? trim($numeroComprobante) : 'Venta #' . $idVenta;
            $nombre = trim((string) ($cliente['NombreApellido'] ?? '')) ?: 'cliente';
            $mensaje = \Lteco\Support\VentaView::mensajePostventa(
                [
                    'IdVenta' => $idVenta,
                    'NumeroFactura' => $comprobante,
                    'NombreApellido' => $nombre,
                ],
                $lineas->garantiaFechaFinPorVenta($idVenta),
                $lineas->serviceFechasProgramadasPorVenta($idVenta, 4)
            );

            $ok = \enviarWhatsAppTextoGratisConPdo(
                $pdo,
                (string) $cliente['Telefono'],
                $mensaje,
                $idVenta,
                'compra_confirmada_cliente'
            );
            if (!$ok) {
                $detalle = \whatsappResumenUltimoError($pdo, 'venta', $idVenta, 'compra_confirmada_cliente');
                if ($detalle !== '' && function_exists('logPanelError')) {
                    \logPanelError('pedido_web_whatsapp_cliente_gratis', $detalle, [
                        'id_venta' => $idVenta,
                        'telefono_normalizado' => \whatsappFormatearTelefono((string) $cliente['Telefono']),
                    ]);
                }

                if (!empty($cfg['tpl_venta'])) {
                    $fmtFecha = static function (?string $fecha): string {
                        if (!$fecha) {
                            return 'No aplica';
                        }
                        try {
                            return (new \DateTimeImmutable($fecha))->format('d/m/Y');
                        } catch (\Throwable) {
                            return 'No aplica';
                        }
                    };
                    $services = array_map($fmtFecha, $lineas->serviceFechasProgramadasPorVenta($idVenta, 4));
                    $services = array_pad(array_slice($services, 0, 4), 4, 'No aplica');
                    \enviarWhatsAppTemplateConPdo(
                        $pdo,
                        (string) $cliente['Telefono'],
                        (string) $cfg['tpl_venta'],
                        [
                            $nombre,
                            $comprobante,
                            $fmtFecha($lineas->garantiaFechaFinPorVenta($idVenta)),
                            $services[0],
                            $services[1],
                            $services[2],
                            $services[3],
                        ],
                        'venta',
                        $idVenta
                    );
                }
            }
        } catch (\Throwable) {
            // No romper la confirmación del pago por un fallo de WhatsApp.
        }
    }

    private function tasaIvaPedido(\PDO $pdo, int $idPedido): float
    {
        $stmt = $pdo->prepare('SELECT TasaIVA FROM ecommerce_pedido_item WHERE IdPedido=? ORDER BY IdItem LIMIT 1');
        $stmt->execute([$idPedido]);
        $itemRate = $stmt->fetchColumn();
        if ($itemRate !== false && (float) $itemRate > 0) {
            return (float) $itemRate;
        }

        $stmt = $pdo->query('SELECT TasaIVA FROM configuracion ORDER BY IdConfiguracion DESC LIMIT 1');
        $config = ConfiguracionComercial::normalizar(['TasaIVA' => $stmt ? $stmt->fetchColumn() : null], 22.0);
        return $config['TasaIVA'];
    }

    /** @param array<string,mixed> $pedido */
    private function resolverClientePedido(\PDO $pdo, array $pedido): int
    {
        $cedula = preg_replace('/\D+/', '', (string) ($pedido['Cedula'] ?? ''));
        $correo = mb_strtolower(trim((string) $pedido['Correo']), 'UTF-8');
        if ($cedula !== '') {
            $stmt = $pdo->prepare('SELECT IdCliente,Correo,Telefono FROM cliente WHERE Cedula=? FOR UPDATE');
            $stmt->execute([$cedula]);
            $cliente = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($cliente) {
                $correoOk = mb_strtolower(trim((string) ($cliente['Correo'] ?? '')), 'UTF-8') === $correo;
                $telOk = preg_replace('/\D+/', '', (string) ($cliente['Telefono'] ?? '')) === preg_replace('/\D+/', '', (string) $pedido['Telefono']);
                if (!$correoOk && !$telOk) {
                    throw new \RuntimeException('La cédula pertenece a otro cliente; verificá su identidad antes de cobrar.');
                }
                return (int) $cliente['IdCliente'];
            }
        }

        $stmt = $pdo->prepare('SELECT IdCliente FROM cliente WHERE LOWER(TRIM(Correo))=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$correo]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        $stmt = $pdo->prepare('INSERT INTO cliente (NombreApellido,Telefono,Correo,Cedula,Direccion) VALUES (?,?,?,?,?)');
        $stmt->execute([trim($pedido['Nombre'] . ' ' . $pedido['Apellido']), $pedido['Telefono'], $correo, $cedula !== '' ? $cedula : null, $pedido['Direccion']]);
        return (int) $pdo->lastInsertId();
    }

    public function solicitarReembolso(int $id, int $idUsuario, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($id <= 0 || $motivo === '') {
            throw new \RuntimeException('Indicá el motivo del reembolso.');
        }

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT Estado,EstadoPago,IdCuenta,Correo FROM ecommerce_pedido WHERE IdPedido=? FOR UPDATE');
            $stmt->execute([$id]);
            $p = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$p) {
                throw new \RuntimeException('Pedido inexistente.');
            }
            if ($p['EstadoPago'] !== 'Aprobado' || !in_array($p['Estado'], ['Pagado', 'Preparando', 'Listo'], true)) {
                throw new \RuntimeException('Este pedido no admite iniciar un reembolso.');
            }

            $pdo->prepare("UPDATE ecommerce_pedido SET Estado='ReembolsoPendiente',EstadoPago='ReembolsoPendiente',MotivoCancelacion=?,VersionBloqueo=VersionBloqueo+1 WHERE IdPedido=?")->execute([$motivo, $id]);
            $pdo->prepare("INSERT IGNORE INTO ecommerce_evento (IdPedido,IdCuenta,Tipo,ClaveIdempotencia,PayloadJson) VALUES (?,?, 'ReembolsoSolicitado',?,?)")->execute([$id, (int) $p['IdCuenta'] ?: null, 'reembolso-pedido-' . $id, json_encode(['motivo' => $motivo], JSON_UNESCAPED_UNICODE)]);
            $pdo->prepare("INSERT IGNORE INTO ecommerce_notificacion (IdCuenta,IdPedido,Tipo,Destinatario) VALUES (?,?,'ReembolsoSolicitado',?)")->execute([(int) $p['IdCuenta'] ?: null, $id, (string) $p['Correo']]);
            $pdo->prepare("INSERT INTO ecommerce_auditoria (IdPedido,IdUsuario,Accion,EstadoAnterior,EstadoNuevo,MetadataJson) VALUES (?,?,'ReembolsoSolicitado',?,'ReembolsoPendiente',?)")->execute([$id, $idUsuario, $p['Estado'], json_encode(['motivo' => $motivo], JSON_UNESCAPED_UNICODE)]);
            $this->encolarAlerta($pdo, $id, 'pedido_web_reembolso', 'warning', 'Reembolso web solicitado', 'El pedido #' . $id . ' requiere gestión de reembolso.', $idUsuario);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listarSolicitudesPrivacidad(): array
    {
        return $this->connection->pdo()->query("SELECT s.*,c.Nombre,c.Apellido,c.Correo FROM ecommerce_solicitud_privacidad s JOIN ecommerce_cuenta c ON c.IdCuenta=s.IdCuenta ORDER BY FIELD(s.Estado,'Pendiente','EnProceso','Resuelta','Rechazada'),s.IdSolicitud DESC LIMIT 200")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function resolverSolicitudPrivacidad(int $id, int $idUsuario, string $estado, string $respuesta): void
    {
        if ($id <= 0 || !in_array($estado, ['EnProceso', 'Resuelta', 'Rechazada'], true)) {
            throw new \RuntimeException('Estado de solicitud no válido.');
        }
        if (in_array($estado, ['Resuelta', 'Rechazada'], true) && trim($respuesta) === '') {
            throw new \RuntimeException('Escribí una respuesta para cerrar la solicitud.');
        }

        $stmt = $this->connection->pdo()->prepare('UPDATE ecommerce_solicitud_privacidad SET Estado=?,Respuesta=?,ResueltaPor=?,ResueltoEn=IF(? IN (\'Resuelta\',\'Rechazada\'),NOW(),NULL) WHERE IdSolicitud=?');
        $stmt->execute([$estado, trim($respuesta) ?: null, $idUsuario, $estado, $id]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Solicitud inexistente.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function listarSolicitudesPrivacidadStorefront(): array
    {
        return $this->connection->pdo()->query("SELECT r.*,u.NombreCompleto ResueltaPorNombre FROM storefront_privacy_request r LEFT JOIN usuario u ON u.IdUsuario=r.ResueltaPor ORDER BY FIELD(r.Estado,'submitted','in_review','resolved','rejected'),r.VenceEn,r.CreadoEn DESC LIMIT 200")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function resolverSolicitudPrivacidadStorefront(string $uuid, int $idUsuario, string $estado, string $respuesta): void
    {
        if (preg_match('/^[0-9a-f-]{36}$/', $uuid) !== 1 || !in_array($estado, ['in_review', 'resolved', 'rejected'], true)) {
            throw new \RuntimeException('Solicitud no válida.');
        }
        if (in_array($estado, ['resolved', 'rejected'], true) && trim($respuesta) === '') {
            throw new \RuntimeException('Escribí la resolución antes de cerrar la solicitud.');
        }

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT Correo,NotificacionEncolada FROM storefront_privacy_request WHERE RequestUuid=? FOR UPDATE');
            $stmt->execute([$uuid]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('Solicitud inexistente.');
            }

            $pdo->prepare("UPDATE storefront_privacy_request SET Estado=?,Respuesta=?,ResueltaPor=?,ResueltaEn=IF(? IN ('resolved','rejected'),NOW(),NULL) WHERE RequestUuid=?")->execute([$estado, trim($respuesta) ?: null, $idUsuario, $estado, $uuid]);
            if (in_array($estado, ['resolved', 'rejected'], true) && !(bool) $row['NotificacionEncolada']) {
                $pdo->prepare("INSERT INTO ecommerce_notificacion (Tipo,Destinatario) VALUES ('PrivacidadResuelta',?)")->execute([(string) $row['Correo']]);
                $pdo->prepare('UPDATE storefront_privacy_request SET NotificacionEncolada=1 WHERE RequestUuid=?')->execute([$uuid]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listar(int $limite = 200, array $filtros = []): array
    {
        $limite = max(1, min($limite, 500));
        $where = [];
        $params = [];
        $estado = trim((string) ($filtros['estado'] ?? ''));
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($estado !== '' && in_array($estado, ['PendientePago', 'PagoEnRevision', 'Pagado', 'Preparando', 'Listo', 'Entregado', 'Cancelado', 'Vencido', 'ReembolsoPendiente', 'Reembolsado', 'ExcepcionPagoSinStock'], true)) {
            $where[] = 'p.Estado=?';
            $params[] = $estado;
        }
        if ($buscar !== '') {
            $where[] = '(p.NumeroPedido LIKE ? OR p.Nombre LIKE ? OR p.Apellido LIKE ? OR p.Correo LIKE ?)';
            $like = '%' . $buscar . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $sql = "SELECT p.*,
                       GROUP_CONCAT(CONCAT(i.Modelo,IF(i.CapacidadBateriaAh IS NULL,'',CONCAT(' · ',i.CapacidadBateriaAh,'Ah')),' · ',COALESCE(i.Color,'')) SEPARATOR ', ') Items,
                       GROUP_CONCAT(NULLIF(v.NumeroMotor,'') SEPARATOR ', ') NumerosMotor
                FROM ecommerce_pedido p
                LEFT JOIN ecommerce_pedido_item i ON i.IdPedido=p.IdPedido
                LEFT JOIN vehiculo v ON v.IdVehiculo=i.IdVehiculo"
                . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
                . " GROUP BY p.IdPedido ORDER BY p.IdPedido DESC LIMIT {$limite}";
        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function metricas(int $dias = 30): array
    {
        $dias = max(1, min($dias, 365));
        $stmt = $this->connection->pdo()->query("SELECT Evento,SUM(Cantidad) Cantidad FROM ecommerce_metrica_diaria WHERE Fecha>=DATE_SUB(CURDATE(),INTERVAL {$dias} DAY) GROUP BY Evento");
        $r = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $f) {
            $r[(string) $f['Evento']] = (int) $f['Cantidad'];
        }
        return $r;
    }

    /** @return array{pedido:array<string,mixed>,items:list<array<string,mixed>>,pagos:list<array<string,mixed>>,auditoria:list<array<string,mixed>>,notificaciones:list<array<string,mixed>>} */
    public function detalle(int $id): array
    {
        if ($id <= 0) {
            throw new \RuntimeException('Pedido inexistente.');
        }

        $pdo = $this->connection->pdo();
        $stmt = $pdo->prepare('SELECT p.*,u.NombreCompleto EntregadoPorNombre FROM ecommerce_pedido p LEFT JOIN usuario u ON u.IdUsuario=p.EntregadoPor WHERE p.IdPedido=?');
        $stmt->execute([$id]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$pedido) {
            throw new \RuntimeException('Pedido inexistente.');
        }

        $stmt = $pdo->prepare('SELECT i.*,v.NumeroMotor FROM ecommerce_pedido_item i LEFT JOIN vehiculo v ON v.IdVehiculo=i.IdVehiculo WHERE i.IdPedido=? ORDER BY i.IdItem');
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare('SELECT * FROM ecommerce_pago WHERE IdPedido=? ORDER BY IdPago DESC');
        $stmt->execute([$id]);
        $pagos = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare('SELECT a.*,u.NombreCompleto UsuarioNombre FROM ecommerce_auditoria a LEFT JOIN usuario u ON u.IdUsuario=a.IdUsuario WHERE a.IdPedido=? ORDER BY a.IdAuditoria DESC');
        $stmt->execute([$id]);
        $auditoria = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare('SELECT * FROM ecommerce_notificacion WHERE IdPedido=? ORDER BY IdNotificacion DESC');
        $stmt->execute([$id]);
        $notificaciones = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        return compact('pedido', 'items', 'pagos', 'auditoria', 'notificaciones');
    }

    public function reintentarNotificacion(int $idPedido, int $idNotificacion, int $idUsuario): void
    {
        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE ecommerce_notificacion SET Estado='Pendiente',Intentos=0,UltimoError=NULL,EnviadaEn=NULL WHERE IdNotificacion=? AND IdPedido=? AND Estado='Fallida'");
            $stmt->execute([$idNotificacion, $idPedido]);
            if ($stmt->rowCount() !== 1) {
                throw new \RuntimeException('Solo se pueden reintentar correos fallidos.');
            }
            $pdo->prepare("INSERT INTO ecommerce_auditoria (IdPedido,IdUsuario,Accion,MetadataJson) VALUES (?,?,'ReintentoCorreo',?)")->execute([$idPedido, $idUsuario, json_encode(['id_notificacion' => $idNotificacion], JSON_THROW_ON_ERROR)]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function registrarErrorOperativo(int $idPedido, int $idUsuario, string $accion, string $mensaje): void
    {
        if ($idPedido <= 0) {
            return;
        }
        $pdo = $this->connection->pdo();
        $mensaje = mb_substr(trim($mensaje), 0, 500);
        try {
            $pdo->prepare("INSERT INTO ecommerce_auditoria (IdPedido,IdUsuario,Accion,MetadataJson) SELECT IdPedido,?,'OperacionFallida',? FROM ecommerce_pedido WHERE IdPedido=?")->execute([$idUsuario, json_encode(['accion' => $accion, 'error' => $mensaje], JSON_UNESCAPED_UNICODE), $idPedido]);
            $this->encolarAlerta($pdo, $idPedido, 'pedido_web_error_' . substr(hash('sha256', $accion . $mensaje), 0, 12), 'warning', 'Error al gestionar pedido web', $mensaje, $idUsuario);
        } catch (\Throwable) {
            // El error original sigue siendo el importante.
        }
    }

    private function encolarAlerta(\PDO $pdo, int $idPedido, string $tipo, string $severidad, string $titulo, string $cuerpo, ?int $idUsuario = null): void
    {
        $stmt = $pdo->prepare("INSERT IGNORE INTO internal_alert (Tipo,Severidad,Titulo,Cuerpo,SourceType,SourceId,ResponsableUsuarioId,FechaEvento) VALUES (?,?,?,?, 'ecommerce_pedido',?,?,NOW())");
        $stmt->execute([$tipo, $severidad, $titulo, $cuerpo, $idPedido, $idUsuario ?: null]);
    }
}
