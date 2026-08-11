<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereModulo("ventas");
require_once __DIR__ . "/../includes/helpers.php";

$connection = new \Lteco\Infrastructure\Db\Connection($pdo);
$ventaRepository = new \Lteco\Infrastructure\Repository\VentaRepository($connection);
$ventaPersistence = new \Lteco\Application\Venta\VentaPersistenceService($ventaRepository);
$ventaLineas = new \Lteco\Application\Venta\VentaLineasService($connection);
$ventaCommercial = new \Lteco\Application\Venta\VentaCommercialService(
    new \Lteco\Infrastructure\Repository\VentaCommercialRepository($connection)
);
$clienteCrud = new \Lteco\Application\Cliente\ClienteCrudService(
    new \Lteco\Infrastructure\Repository\ClienteCrudRepository($connection)
);
$gastoCrud = new \Lteco\Application\Gasto\GastoCrudService(
    new \Lteco\Infrastructure\Repository\GastoCrudRepository($connection)
);

try {
    requirePost();
    verifyCsrfOrFail();

    $tipoCambioConfig = obtenerTipoCambioUSD($pdo);
    $configComercial = $ventaCommercial->configuracion(defaultTasaIVA());

    $descuentoContado = (float)$configComercial['DescuentoContado'];
    $recargoTarjeta = (float)$configComercial['RecargoTarjeta'];
    $comisionDistribuidorPct = (float)$configComercial['ComisionDistribuidor'];

    // Venta directa: comisión propia del usuario que registra la venta.
    $idUsuarioVendedor = (int)(usuarioActual()['IdUsuario'] ?? 0);
    $comisionVendedorPct = $ventaCommercial->comisionVendedor($idUsuarioVendedor);

    $tasaIVA = (float)$configComercial['TasaIVA'];

    $clienteId = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
    $monedaVenta = trim((string)($_POST['moneda_venta'] ?? 'UYU'));
    $tipoCambio = decimalNoNegativo($_POST['tipo_cambio'] ?? $tipoCambioConfig, $tipoCambioConfig);
    $tipoCliente = trim((string)($_POST['tipo_cliente'] ?? 'Final'));
    $distribuidorId = !empty($_POST['distribuidor_id']) ? (int)$_POST['distribuidor_id'] : null;

    if ($tipoCliente === 'Distribuidor' && !esAdmin()) {
        registrarAuditoria(
            $pdo,
            'INTENTO_VENTA_DISTRIBUIDOR_NO_AUTORIZADO',
            'Ventas',
            'Intento bloqueado: el usuario no tiene permisos para registrar ventas de distribuidor.',
            ['rol' => rolActual(), 'id_usuario' => (int)(usuarioActual()['IdUsuario'] ?? 0)]
        );
        throw new RuntimeException('Solo administradores pueden registrar ventas de tipo Distribuidor.');
    }

    // Si es venta de distribuidor:
    // - comisión del distribuidor desde la tabla distribuidor
    // - comisión interna desde el usuario configurado en configuracion.UsuarioComisionDistribuidorId
    if ($tipoCliente === 'Distribuidor') {
        if ($distribuidorId) {
            $comisionDistribuidorPct = $ventaCommercial->comisionDistribuidor(
                $distribuidorId,
                $comisionDistribuidorPct
            );
        }

        $usrComisionDist = $ventaCommercial->usuarioInternoDistribuidor(ROL_DISTRIBUIDOR);
        $idUsuarioVendedor = $usrComisionDist['IdUsuario'];
        $comisionVendedorPct = $usrComisionDist['ComisionDistribuidorPct'];
    }
    $metodoPago = trim((string)($_POST['metodo_pago'] ?? 'Efectivo'));
    $tipoTarjeta = limpiarTextoOpcional($_POST['tipo_tarjeta'] ?? null);
    $marcaTarjeta = limpiarTextoOpcional($_POST['marca_tarjeta'] ?? null);
    $cuotasTarjeta = null;
    $observaciones = limpiarTextoOpcional($_POST['observaciones'] ?? null);
    $numeroFacturaManual = limpiarTextoOpcional($_POST['numero_factura_manual'] ?? null);
    if ($numeroFacturaManual !== null) {
        $numeroFacturaManual = preg_replace('/[^a-zA-Z0-9\-\/]/', '', $numeroFacturaManual);
        $numeroFacturaManual = $numeroFacturaManual !== '' ? mb_substr($numeroFacturaManual, 0, 40) : null;
    }

    if (!in_array($monedaVenta, monedasSistema(), true)) {
        throw new RuntimeException('Moneda de venta no válida.');
    }

    if ($tipoCambio <= 0) {
        $tipoCambio = $tipoCambioConfig;
    }

    if (!in_array($tipoCliente, tiposClienteSistema(), true)) {
        $tipoCliente = 'Final';
    }

    if (!in_array($metodoPago, metodosPagoVentaSistema(), true)) {
        $metodoPago = 'Efectivo';
    }

    if ($metodoPago === 'Tarjeta') {
        if ($tipoTarjeta === null || !in_array($tipoTarjeta, tiposTarjetaSistema(), true)) {
            throw new RuntimeException('Seleccioná si la tarjeta es crédito o débito.');
        }

        if ($marcaTarjeta === null || !in_array($marcaTarjeta, marcasTarjetaSistema(), true)) {
            throw new RuntimeException('Seleccioná la marca de la tarjeta.');
        }

        if ($tipoTarjeta === 'Crédito') {
            $cuotasTarjeta = enteroNoNegativo($_POST['cuotas_tarjeta'] ?? null);
            if (!in_array($cuotasTarjeta, cuotasTarjetaSistema(), true)) {
                throw new RuntimeException('Seleccioná una cantidad de cuotas válida.');
            }

            // LTECO:VENTA_EXCEL_CUOTAS_EXACTAS_BACKEND_V3
            // LTECO:FIX_MASTERCARD_6_O_18_CUOTAS
            $cuotasPermitidasMarca = cuotasTarjetaPorMarcaSistema()[$marcaTarjeta] ?? [];
            if (!in_array($cuotasTarjeta, $cuotasPermitidasMarca, true)) {
                throw new RuntimeException($marcaTarjeta . ' debe registrarse en ' . implode(' o ', $cuotasPermitidasMarca) . ' cuotas.');
            }
        } else {
            $cuotasTarjeta = null;
        }
    } else {
        $tipoTarjeta = null;
        $marcaTarjeta = null;
        $cuotasTarjeta = null;
    }

    $vehiculosSeleccionados = \Lteco\Domain\Venta\SeleccionProductos::vehiculos($_POST);
    $repuestosSolicitados = \Lteco\Domain\Venta\SeleccionProductos::repuestos($_POST);

    if (!\Lteco\Domain\Venta\SeleccionProductos::tieneProductos($vehiculosSeleccionados, $repuestosSolicitados)) {
        throw new RuntimeException('Tenés que vender al menos una moto o un repuesto.');
    }

    $idempotencyKey = panelIdempotencyRequestKey();
    $idempotencyHash = panelIdempotencyRequestHash('panel.venta.crear', $_POST);

    $pdo->beginTransaction();
    $idempotencyRow = panelIdempotencyClaim(
        $pdo,
        'panel.venta.crear',
        $idempotencyKey,
        $idempotencyHash,
        (int)(usuarioActual()['IdUsuario'] ?? 0)
    );
    if ($idempotencyRow !== null) {
        $pdo->commit();
        redirect((string)($idempotencyRow['RedirectUrl'] ?: panelBaseUrl('ventas/index.php')));
    }

    if (!$clienteId) {
        $nuevoTipoFiscal = trim((string)($_POST['nuevo_tipo_fiscal'] ?? 'Consumidor final'));
        $nuevoNombre = normalizarTextoHumano($_POST['nuevo_nombre'] ?? '', 80);
        $nuevoApellido = normalizarTextoHumano($_POST['nuevo_apellido'] ?? '', 80);
        $nuevoTelefono = normalizarTelefono($_POST['nuevo_telefono'] ?? null);
        $nuevoCorreo = limpiarTextoOpcional(mb_strtolower(normalizarTextoHumano($_POST['nuevo_correo'] ?? '', 150), 'UTF-8'));
        $nuevoCedula = normalizarCedula($_POST['nuevo_cedula'] ?? '');
        $nuevoDireccion = limpiarTextoOpcional(normalizarTextoHumano($_POST['nuevo_direccion'] ?? '', 255));
        $nuevoRut = limpiarTextoOpcional(normalizarTextoHumano($_POST['nuevo_rut'] ?? '', 40));

        if (!in_array($nuevoTipoFiscal, tiposClienteFiscalSistema(), true)) {
            $nuevoTipoFiscal = 'Consumidor final';
        }

        if ($nuevoNombre === '' || ($nuevoTipoFiscal === 'Consumidor final' && $nuevoApellido === '')) {
            throw new RuntimeException($nuevoTipoFiscal === 'Empresa/RUT'
                ? 'Tenés que seleccionar un cliente o cargar la razón social del cliente nuevo.'
                : 'Tenés que seleccionar un cliente o cargar nombre y apellido del cliente nuevo.');
        }

        if ($nuevoTipoFiscal === 'Empresa/RUT' && $nuevoRut === null) {
            throw new RuntimeException('El RUT es obligatorio para clientes Empresa/RUT.');
        }

        if ($nuevoTipoFiscal === 'Consumidor final') {
            $nuevoRut = null;
        }

        if ($nuevoTelefono !== null && !telefonoValido($nuevoTelefono)) {
            throw new RuntimeException('El teléfono del cliente nuevo no tiene un formato válido.');
        }

        if ($nuevoCorreo !== null && !filter_var($nuevoCorreo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El correo del cliente nuevo no tiene un formato válido.');
        }

        if ($nuevoTelefono !== null && !$clienteCrud->telefonoDisponible($nuevoTelefono, null)) {
            $mensajeDuplicado = esVendedor()
                ? 'Existe un cliente registrado con esos datos. Solicitá a administración si necesitás asociarlo.'
                : 'Ya existe un cliente con ese teléfono. Seleccionalo desde la lista.';
            throw new RuntimeException($mensajeDuplicado);
        }

        if ($nuevoCorreo !== null && !$clienteCrud->correoDisponible($nuevoCorreo, null)) {
            $mensajeDuplicado = esVendedor()
                ? 'Existe un cliente registrado con esos datos. Solicitá a administración si necesitás asociarlo.'
                : 'Ya existe un cliente con ese correo. Seleccionalo desde la lista.';
            throw new RuntimeException($mensajeDuplicado);
        }

        if ($nuevoTipoFiscal === 'Consumidor final' && $nuevoCedula === '') {
            throw new RuntimeException('La cédula es obligatoria para el cliente nuevo (Consumidor final).');
        }

        if ($nuevoCedula !== '' && !cedulaUruguayaValida($nuevoCedula)) {
            throw new RuntimeException('La cédula del cliente nuevo no es válida.');
        }

        if ($nuevoCedula !== '' && !$clienteCrud->cedulaDisponible($nuevoCedula, null)) {
            throw new RuntimeException(esVendedor()
                ? 'Existe un cliente registrado con esos datos. Solicitá a administración si necesitás asociarlo.'
                : 'Ya existe un cliente con esa cédula. Seleccionalo desde la lista.');
        }

        $clienteId = $clienteCrud->crear([
            'nombre_apellido' => trim($nuevoNombre . ' ' . $nuevoApellido),
            'telefono' => $nuevoTelefono,
            'correo' => $nuevoCorreo,
            'tipo_fiscal' => $nuevoTipoFiscal,
            'cedula' => limpiarTextoOpcional($nuevoCedula),
            'direccion' => $nuevoDireccion,
            'rut' => $nuevoRut,
        ]);

        registrarAuditoria($pdo, 'CREAR_CLIENTE', 'Clientes', 'Cliente creado desde venta: ' . $nuevoNombre . ' ' . $nuevoApellido, [
            'id_cliente' => $clienteId,
            'tipo_fiscal' => $nuevoTipoFiscal,
        ]);
    } else {
        if (!$clienteCrud->existe($clienteId)) {
            throw new RuntimeException('El cliente seleccionado no existe.');
        }

        if (esVendedor() && !vendedorPuedeVerCliente($pdo, $clienteId)) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            denegarAcceso('No tenés permisos para asociar ese cliente a una venta.');
        }
    }

    $idVenta = $ventaPersistence->crearCabecera([
        'clienteId' => $clienteId,
        'metodoPago' => $metodoPago,
        'tipoCliente' => $tipoCliente,
        'distribuidorId' => $distribuidorId,
        'moneda' => $monedaVenta,
        'observaciones' => $observaciones,
        'tipoCambio' => $tipoCambio,
        'tipoTarjeta' => $tipoTarjeta,
        'marcaTarjeta' => $marcaTarjeta,
        'cuotasTarjeta' => $cuotasTarjeta,
        'usuarioVendedorId' => (int)(usuarioActual()['IdUsuario'] ?? 0),
    ]);

    // LTECO:FIX_NUMERO_FACTURA_MANUAL — si viene manual tiene prioridad
    $numeroFactura = ($numeroFacturaManual !== null && $numeroFacturaManual !== '')
        ? $numeroFacturaManual
        : \Lteco\Domain\Venta\FacturaInterna::generar($idVenta);

    $ventaPersistence->asignarNumeroFactura($idVenta, $numeroFactura);

    $total = 0.0;
    $ganancia = 0.0;
    $costoTotal = 0.0;
    $tipoCambioLineasPeso = 0.0;
    $tipoCambioLineasTotal = 0.0;
    $cantidadMotos = 0;
    $cantidadRepuestos = 0;
    $primeraMotaWaModelo = '';
    $primeraMotaWaMotor  = '';

    foreach ($vehiculosSeleccionados as $idVehiculo) {
        $data = $ventaLineas->bloquearVehiculo($idVehiculo);

        if (!$data) {
            throw new RuntimeException('Vehículo no encontrado: ' . $idVehiculo);
        }

        if ($cantidadMotos === 0) {
            $primeraMotaWaModelo = (string)($data['Modelo'] ?? '');
            $primeraMotaWaMotor  = (string)($data['NumeroMotor'] ?? '');
        }

        if (in_array((string)$data['Estado'], ['Vendido', 'Sin stock', 'Oculto'], true)) {
            throw new RuntimeException('El vehículo ' . $idVehiculo . ' no está disponible para la venta.');
        }

        if ($ventaLineas->vehiculoTieneFechaVenta($idVehiculo)) {
            throw new RuntimeException('El vehículo ' . $idVehiculo . ' ya tiene fecha de venta registrada.');
        }
        if ($ventaLineas->productoTieneVentaActiva((int) $data['IdProducto'])) {
            throw new RuntimeException('El vehículo ' . $idVehiculo . ' ya tiene una venta no anulada.');
        }

        $precioBase = \Lteco\Domain\Venta\ProductoVenta::precioBase($data, $tipoCliente);

        if ($precioBase <= 0) {
            throw new RuntimeException('El vehículo ' . $idVehiculo . ' no tiene precio de venta válido.');
        }

        if ($data['Estado'] === 'Reservado' && $data['ClienteReservaId'] && (int)$data['ClienteReservaId'] !== (int)$clienteId) {
            throw new RuntimeException('El vehículo ' . $idVehiculo . ' está reservado para otro cliente.');
        }

        $tipoCambioLinea = \Lteco\Domain\Venta\ProductoVenta::tipoCambio(
            $data,
            $tipoCambioConfig,
            defaultTipoCambioUSD()
        );
        $precioConvertido = round(convertirMoneda($precioBase, (string)$data['Moneda'], $monedaVenta, $tipoCambioLinea), 2);
        $gastoConvertido = round(convertirMoneda((float)$data['GastoTotal'], (string)$data['Moneda'], $monedaVenta, $tipoCambioLinea), 2);
        $gananciaLinea = round($precioConvertido - $gastoConvertido, 2);

        // Líneas de venta delegadas al writer compartido (Wave 1 migración POO):
        // detalle + producto('Vendido') + vehiculo(FechaVenta) + garantía + services.
        // Comportamiento idéntico al inline previo; congelado por
        // tests/integration/VentaLineasServiceTest.php. Transaction-agnostic.
        $ventaLineas->registrarVehiculo([
            'idVenta'        => $idVenta,
            'idVehiculo'     => $idVehiculo,
            'idProducto'     => (int)$data['IdProducto'],
            'clienteId'      => $clienteId,
            'precioUnitario' => $precioConvertido,
            'costoUnitario'  => $gastoConvertido,
            'subtotal'       => $precioConvertido,
            'gananciaLinea'  => $gananciaLinea,
            'moneda'         => $monedaVenta,
        ]);

        $total += $precioConvertido;
        $ganancia += $gananciaLinea;
        $costoTotal += $gastoConvertido;
        $tipoCambioLineasPeso += $tipoCambioLinea * max(0.01, abs($precioConvertido));
        $tipoCambioLineasTotal += max(0.01, abs($precioConvertido));
        $cantidadMotos++;
    }

    foreach ($repuestosSolicitados as $idRep => $cantidad) {
        $data = $ventaLineas->bloquearRepuesto($idRep);

        if (!$data) {
            throw new RuntimeException('Repuesto no encontrado.');
        }

        if ((string)$data['Estado'] === 'Oculto') {
            throw new RuntimeException('El repuesto ' . $data['Nombre'] . ' está oculto y no se puede vender.');
        }

        $precioBase = \Lteco\Domain\Venta\ProductoVenta::precioBase($data, $tipoCliente);

        if ($precioBase <= 0) {
            throw new RuntimeException('El repuesto ' . $data['Nombre'] . ' no tiene precio de venta válido.');
        }

        if ($cantidad > (int)$data['Stock']) {
            throw new RuntimeException('No hay suficiente stock para el repuesto ' . $data['Nombre'] . '.');
        }

        $tipoCambioLinea = \Lteco\Domain\Venta\ProductoVenta::tipoCambio(
            $data,
            $tipoCambioConfig,
            defaultTipoCambioUSD()
        );
        $precioConvertido = round(convertirMoneda($precioBase, (string)$data['Moneda'], $monedaVenta, $tipoCambioLinea), 2);
        $gastoConvertido = round(convertirMoneda((float)$data['GastoTotal'], (string)$data['Moneda'], $monedaVenta, $tipoCambioLinea), 2);
        $subtotal = round($precioConvertido * $cantidad, 2);
        $gananciaLinea = round(($precioConvertido - $gastoConvertido) * $cantidad, 2);

        $nuevoStock = (int)$data['Stock'] - $cantidad;
        $nuevoEstado = normalizarEstadoRepuesto((string)$data['Estado'], $nuevoStock);

        // Línea de repuesto delegada al writer compartido: detalle + stock/estado.
        $ventaLineas->registrarRepuesto([
            'idVenta'        => $idVenta,
            'idProducto'     => (int)$data['IdProducto'],
            'cantidad'       => $cantidad,
            'precioUnitario' => $precioConvertido,
            'costoUnitario'  => $gastoConvertido,
            'subtotal'       => $subtotal,
            'gananciaLinea'  => $gananciaLinea,
            'moneda'         => $monedaVenta,
            'nuevoStock'     => $nuevoStock,
            'nuevoEstado'    => $nuevoEstado,
        ]);

        $total += $subtotal;
        $ganancia += $gananciaLinea;
        $costoTotal += round($gastoConvertido * $cantidad, 2);
        $tipoCambioLineasPeso += $tipoCambioLinea * max(0.01, abs($subtotal));
        $tipoCambioLineasTotal += max(0.01, abs($subtotal));
        $cantidadRepuestos += $cantidad;
    }

    $tipoCambioVentaFinal = \Lteco\Domain\Venta\ProductoVenta::tipoCambioPromedio(
        $tipoCambioLineasPeso,
        $tipoCambioLineasTotal,
        $tipoCambio
    );

    $subtotalBruto = round($total, 2);

    /*
     * Cálculo comercial puro extraído a Lteco\Domain\Venta\ReglasComerciales.
     * Preserva exactamente la lógica previa (cubierta por tests/Characterization,
     * "php tests/run.php"):
     * - Transferencia se comporta como Efectivo (contado); descuento contado solo en contado.
     * - LTECO:VENTA_EXCEL_TARJETA_SIN_RECARGO_CLIENTE_V3: la tarjeta NO sube el total
     *   cobrado; su comisión es costo financiero interno (débito 2%, crédito = recargo).
     * - LTECO:VENTA_EXCEL_COMISION_DISTRIBUIDOR_TOTAL_CON_IVA_V3: comisión distribuidor
     *   sobre el total CON IVA (ej. 63000 * 6.67% = 4202.10).
     */
    $calculoComercial = \Lteco\Domain\Venta\ReglasComerciales::calcular([
        'subtotalBruto'           => $subtotalBruto,
        'metodoPago'              => $metodoPago,
        'tipoTarjeta'             => $tipoTarjeta,
        'tipoCliente'             => $tipoCliente,
        'costoTotal'              => $costoTotal,
        'descuentoContadoPct'     => $descuentoContado,
        'recargoTarjetaPct'       => $recargoTarjeta,
        'comisionDistribuidorPct' => $comisionDistribuidorPct,
        'comisionVendedorPct'     => $comisionVendedorPct,
        'tasaIVA'                 => $tasaIVA,
    ]);

    $descuentoAplicado    = $calculoComercial['descuentoAplicado'];
    $baseLuegoDescuento   = $calculoComercial['baseLuegoDescuento'];
    $recargoAplicado      = $calculoComercial['recargoAplicado'];
    $comisionTarjetaPct   = $calculoComercial['comisionTarjetaPct'];
    $comisionTarjeta      = $calculoComercial['comisionTarjeta'];
    $total                = $calculoComercial['total'];
    $montoIVA             = $calculoComercial['montoIVA'];
    $totalSinIVA          = $calculoComercial['totalSinIVA'];
    $comisionVendedor     = $calculoComercial['comisionVendedor'];
    $comisionDistribuidor = $calculoComercial['comisionDistribuidor'];
    $ganancia             = $calculoComercial['ganancia'];

    $estadoPago = \Lteco\Domain\Venta\EstadoPago::resolver($total, $_POST['monto_pagado'] ?? '');
    $montoPagado = $estadoPago['montoPagado'];
    $saldoPendiente = $estadoPago['saldoPendiente'];
    $estadoVentaFinal = $estadoPago['estadoVenta'];

    $ventaPersistence->cerrarVenta($idVenta, [
        'total' => $total,
        'ganancia' => $ganancia,
        'montoPagado' => $montoPagado,
        'saldoPendiente' => $saldoPendiente,
        'estadoVenta' => $estadoVentaFinal,
        'subtotalBruto' => $subtotalBruto,
        'descuentoAplicado' => $descuentoAplicado,
        'recargoAplicado' => $recargoAplicado,
        'comisionTarjeta' => $comisionTarjeta,
        'comisionDistribuidor' => $comisionDistribuidor,
        'comisionVendedor' => $comisionVendedor,
        'montoIVA' => $montoIVA,
        'totalSinIVA' => $totalSinIVA,
        'tipoCambio' => $tipoCambioVentaFinal,
    ]);

    $gastoCrud->registrarComisionesVenta([
        'id_venta' => $idVenta,
        'moneda' => $monedaVenta,
        'tipo_cliente' => $tipoCliente,
        'marca_tarjeta' => $marcaTarjeta ?? '',
        'tipo_tarjeta' => $tipoTarjeta ?? '',
        'cuotas_tarjeta' => $cuotasTarjeta ?? 0,
        'comision_tarjeta' => $comisionTarjeta,
        'comision_tarjeta_pct' => $comisionTarjetaPct ?? 0,
        'base_tarjeta' => $baseLuegoDescuento ?? $total,
        'comision_distribuidor' => $comisionDistribuidor,
        'comision_distribuidor_pct' => $comisionDistribuidorPct,
        'comision_vendedor' => $comisionVendedor,
        'comision_vendedor_pct' => $comisionVendedorPct,
        'total' => $total,
    ]);

    $ventaRedirectUrl = panelBaseUrl('ventas/detalle.php?id=' . urlencode((string)$idVenta) . '&venta_creada=1');
    panelIdempotencyComplete($pdo, $idempotencyKey, 'venta', $idVenta, $ventaRedirectUrl);

    $pdo->commit();

    // WhatsApp: confirmación automática gratuita solo si el cliente abrió ventana de 24 h.
    try {
        require_once __DIR__ . '/../includes/whatsapp.php';
        $waCfgVenta = whatsappObtenerConfig($pdo);
        if ($waCfgVenta['enabled']) {
            $waCliente = $ventaLineas->clienteWhatsapp($clienteId);
            if ($waCliente && !empty($waCliente['Telefono'])) {
                $waComprobante = $numeroFactura ?: ('Venta #' . $idVenta);
                $waNombre = trim((string)($waCliente['NombreApellido'] ?? '')) ?: 'cliente';
                $waMensaje = \Lteco\Support\VentaView::mensajePostventa(
                    [
                        'IdVenta' => $idVenta,
                        'NumeroFactura' => $waComprobante,
                        'NombreApellido' => $waNombre,
                    ],
                    $ventaLineas->garantiaFechaFinPorVenta($idVenta),
                    $ventaLineas->serviceFechasProgramadasPorVenta($idVenta, 4)
                );

                $waOk = enviarWhatsAppTextoGratisConPdo(
                    $pdo,
                    $waCliente['Telefono'],
                    $waMensaje,
                    $idVenta,
                    'compra_confirmada_cliente'
                );
                if (!$waOk) {
                    $detalleWa = whatsappResumenUltimoError($pdo, 'venta', $idVenta, 'compra_confirmada_cliente');
                    if ($detalleWa !== '') {
                        logPanelError('venta_whatsapp_gratis', $detalleWa, [
                            'id_venta' => $idVenta,
                            'telefono_normalizado' => whatsappFormatearTelefono($waCliente['Telefono']),
                        ]);
                    }

                    if (!empty($waCfgVenta['tpl_venta'])) {
                        $fmtFechaWa = static function (?string $fecha): string {
                            if (!$fecha) {
                                return 'No aplica';
                            }
                            try {
                                return (new DateTimeImmutable($fecha))->format('d/m/Y');
                            } catch (Throwable) {
                                return 'No aplica';
                            }
                        };
                        $waServices = array_map(
                            static fn($fecha) => $fmtFechaWa((string)$fecha),
                            $ventaLineas->serviceFechasProgramadasPorVenta($idVenta, 4)
                        );
                        $waServices = array_pad(array_slice($waServices, 0, 4), 4, 'No aplica');

                        enviarWhatsAppTemplate(
                            $waCliente['Telefono'],
                            $waCfgVenta['tpl_venta'],
                            [
                                $waNombre,
                                $waComprobante,
                                $fmtFechaWa($ventaLineas->garantiaFechaFinPorVenta($idVenta)),
                                $waServices[0],
                                $waServices[1],
                                $waServices[2],
                                $waServices[3],
                            ],
                            'venta',
                            $idVenta
                        );
                    }
                }
            }
        }
    } catch (Throwable) {}

    // Notificación email
    try {
        require_once __DIR__ . '/../notificaciones/eventos.php';
        notificarNuevaVenta(
            ['IdVenta' => $idVenta, 'NumeroFactura' => $_POST['numero_factura'] ?? null, 'MetodoPago' => $metodoPago],
            $nombreCliente ?? 'Cliente #' . $clienteId,
            simboloMoneda($monedaVenta) . number_format($total, 2, ',', '.')
        );
    } catch (Throwable) {}

    registrarAuditoria($pdo, 'CREAR_VENTA', 'Ventas', 'Venta #' . $idVenta . ' registrada desde el panel interno.', [
        'id_venta' => $idVenta,
        'id_cliente' => $clienteId,
        'moneda' => $monedaVenta,
        'tipo_cambio' => $tipoCambioVentaFinal,
        'metodo_pago' => $metodoPago,
        'tipo_tarjeta' => $tipoTarjeta,
        'marca_tarjeta' => $marcaTarjeta,
        'cuotas_tarjeta' => $cuotasTarjeta,
        'motos' => $cantidadMotos,
        'repuestos' => $cantidadRepuestos,
        'total' => $total,
        'monto_pagado' => $montoPagado ?? null,
        'saldo_pendiente' => $saldoPendiente ?? null,
        'estado_venta' => $estadoVentaFinal ?? null,
        'subtotal_bruto' => $subtotalBruto,
        'descuento_aplicado' => $descuentoAplicado,
        'recargo_aplicado' => $recargoAplicado,
        'comision_tarjeta' => $comisionTarjeta,
        'comision_distribuidor' => $comisionDistribuidor,
        'monto_iva' => $montoIVA,
        'total_sin_iva' => $totalSinIVA,
        'costo_total' => $costoTotal,
    ]);

    redirectWithFlash(
        $ventaRedirectUrl,
        'success',
        'Venta #' . $idVenta . ' creada correctamente. Podés abrir el comprobante, enviarlo por WhatsApp o registrar una nueva venta.'
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logPanelError('guardar_venta', $e, vehicleActionContext());
    logPanelError('venta_guardar', $e);

    $mensajeUsuario = ($e instanceof RuntimeException || $e instanceof InvalidArgumentException)
        ? $e->getMessage()
        : 'No se pudo guardar la venta. Revisá cliente, stock, método de pago y productos seleccionados.';

    redirect(panelBaseUrl('ventas/crear.php') . '?' . buildQuery(['error' => $mensajeUsuario]));
}
