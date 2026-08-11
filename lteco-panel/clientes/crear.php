<?php
$pageTitle = "Nuevo cliente | Lteco";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . '/../includes/flash.php';
requiereModulo("clientes");
require_once __DIR__ . "/../includes/helpers.php";

$service = new \Lteco\Application\Cliente\ClienteCrudService(
    new \Lteco\Infrastructure\Repository\ClienteCrudRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
);

$mensaje = '';
$errores = [];
$form = [
    'tipo_fiscal' => 'Consumidor final',
    'nombre_apellido' => '',
    'telefono' => '',
    'correo' => '',
    'cedula' => '',
    'direccion' => '',
    'rut' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requirePost();
        verifyCsrfOrFail();
    } catch (Throwable $e) {
        redirectWithFlash(panelBaseUrl('clientes/crear.php'), 'error', 'La sesión del formulario venció o el envío no es válido. Volvé a intentar.');
    }

    foreach (array_keys($form) as $campo) {
        $form[$campo] = trim((string)($_POST[$campo] ?? ''));
    }

    $form['nombre_apellido'] = normalizarTextoHumano($form['nombre_apellido'], 150);
    $form['telefono'] = (string)(normalizarTelefono($form['telefono']) ?? '');
    $form['correo'] = mb_strtolower(normalizarTextoHumano($form['correo'], 150), 'UTF-8');
    $form['cedula'] = normalizarCedula($form['cedula']);
    $form['direccion'] = normalizarTextoHumano($form['direccion'], 255);
    $form['rut'] = normalizarTextoHumano($form['rut'], 40);

    if (!in_array($form['tipo_fiscal'], tiposClienteFiscalSistema(), true)) {
        $form['tipo_fiscal'] = 'Consumidor final';
    }

    if ($form['nombre_apellido'] === '') {
        $errores[] = $form['tipo_fiscal'] === 'Empresa/RUT'
            ? 'La razón social es obligatoria.'
            : 'El nombre y apellido es obligatorio.';
    }

    if ($form['tipo_fiscal'] === 'Empresa/RUT' && $form['rut'] === '') {
        $errores[] = 'El RUT es obligatorio para clientes Empresa/RUT.';
    }

    if ($form['tipo_fiscal'] === 'Consumidor final') {
        $form['rut'] = '';
    }

    if ($form['telefono'] !== '' && !telefonoValido($form['telefono'])) {
        $errores[] = 'El teléfono no tiene un formato válido.';
    }

    if ($form['correo'] !== '' && !filter_var($form['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no tiene un formato válido.';
    }

    if ($form['telefono'] !== '' && !$service->telefonoDisponible($form['telefono'], null)) {
        $errores[] = esVendedor()
            ? 'Existe un cliente registrado. Solicitá a administración si necesitás asociarlo.'
            : 'Ya existe un cliente con ese teléfono.';
    }

    if ($form['correo'] !== '' && !$service->correoDisponible($form['correo'], null)) {
        $errores[] = esVendedor()
            ? 'Existe un cliente registrado. Solicitá a administración si necesitás asociarlo.'
            : 'Ya existe un cliente con ese correo.';
    }

    if ($form['tipo_fiscal'] === 'Consumidor final' && $form['cedula'] === '') {
        $errores[] = 'La cédula es obligatoria para Consumidor final.';
    } elseif ($form['cedula'] !== '') {
        if (!cedulaUruguayaValida($form['cedula'])) {
            $errores[] = 'La cédula no es válida (verificá el número y el dígito verificador).';
        } elseif (!$service->cedulaDisponible($form['cedula'], null)) {
            $errores[] = esVendedor()
                ? 'Existe un cliente registrado. Solicitá a administración si necesitás asociarlo.'
                : 'Ya existe un cliente con esa cédula.';
        }
    }

    if (empty($errores)) {
        try {
            $idCliente = $service->crear([
                'nombre_apellido' => $form['nombre_apellido'],
                'telefono' => limpiarTextoOpcional($form['telefono']),
                'correo' => limpiarTextoOpcional($form['correo']),
                'tipo_fiscal' => $form['tipo_fiscal'],
                'cedula' => limpiarTextoOpcional($form['cedula']),
                'direccion' => limpiarTextoOpcional($form['direccion']),
                'rut' => limpiarTextoOpcional($form['rut']),
            ]);

            registrarAuditoria($pdo, 'CREAR_CLIENTE', 'Clientes', 'Cliente creado: ' . $form['nombre_apellido'], [
                'id_cliente' => $idCliente,
                'telefono' => $form['telefono'],
                'correo' => $form['correo'],
                'tipo_fiscal' => $form['tipo_fiscal'],
                'cedula' => $form['cedula'],
                'rut' => $form['rut'],
            ]);

            redirectWithFlash(panelBaseUrl('clientes/detalle.php?id=' . urlencode((string)$idCliente)), 'success', 'Cliente creado correctamente. Las compras se cargan desde Ventas.');
        } catch (Throwable $e) {
            logPanelError('crear_cliente', $e, ['post_keys' => array_keys($_POST ?? [])]);
            $errores[] = mensajeErrorSeguro($e, 'Error al guardar el cliente.');
        }
    }
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Nuevo cliente</h1>
            <p class="subtle">Carga solo los datos del cliente. Las compras quedan registradas desde el módulo Ventas.</p>
        </div>
        <a class="btn-secondary" href="<?= panelBaseUrl('clientes/index.php') ?>">Volver</a>
    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <div class="section-box">
        <?php if ($mensaje): ?>
            <div class="notice notice--success"><?= h($mensaje, '') ?></div>
        <?php endif; ?>

        <?php if ($errores): ?>
            <div class="notice notice--error">
                <strong>No se pudo guardar.</strong>
                <ul class="notice-list">
                    <?php foreach ($errores as $error): ?>
                        <li><?= h($error, '') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfInput() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Tipo de cliente</label>
                    <select name="tipo_fiscal" id="tipo_fiscal" required>
                        <?php foreach (tiposClienteFiscalSistema() as $tipoFiscal): ?>
                            <option value="<?= h($tipoFiscal, '') ?>" <?= $form['tipo_fiscal'] === $tipoFiscal ? 'selected' : '' ?>>
                                <?= h($tipoFiscal, '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label id="nombre_apellido_label">Nombre y Apellido</label>
                    <input type="text" name="nombre_apellido" id="nombre_apellido" value="<?= h($form['nombre_apellido'], '') ?>" maxlength="150" required>
                </div>

                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" value="<?= h($form['telefono'], '') ?>" maxlength="20">
                </div>

                <div class="form-group">
                    <label>Correo electrónico</label>
                    <input type="email" name="correo" value="<?= h($form['correo'], '') ?>" maxlength="150">
                </div>

                <div class="form-group">
                    <label id="cedula_label">Cédula</label>
                    <input type="text" name="cedula" id="cedula" value="<?= h($form['cedula'], '') ?>" maxlength="40" inputmode="numeric">
                </div>

                <div class="form-group" id="rut_field">
                    <label>RUT</label>
                    <input type="text" name="rut" id="rut" value="<?= h($form['rut'], '') ?>" maxlength="40">
                </div>

                <div class="form-group full">
                    <label>Dirección</label>
                    <input type="text" name="direccion" value="<?= h($form['direccion'], '') ?>" maxlength="255">
                </div>
            </div>

            <div class="notice notice--info u-mt-16">
                Para asociar una moto o repuesto a este cliente, registrá una venta desde <strong>Ventas → Nueva venta</strong>.
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Guardar cliente</button>
            </div>
        </form>
    </div>
</main>

<script nonce="<?= cspNonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
    const tipoFiscal = document.getElementById('tipo_fiscal');
    const nombreLabel = document.getElementById('nombre_apellido_label');
    const rutInput = document.getElementById('rut');
    const cedulaInput = document.getElementById('cedula');
    const cedulaLabel = document.getElementById('cedula_label');

    function actualizarTipoFiscal() {
        const esEmpresa = tipoFiscal && tipoFiscal.value === 'Empresa/RUT';
        if (nombreLabel) nombreLabel.textContent = esEmpresa ? 'Razón social' : 'Nombre y Apellido';
        const rutField = document.getElementById('rut_field');
        if (rutField) rutField.hidden = !esEmpresa;
        if (rutInput) {
            rutInput.required = esEmpresa;
            rutInput.disabled = !esEmpresa;
            if (!esEmpresa) rutInput.value = '';
        }
        if (cedulaInput) cedulaInput.required = !esEmpresa;
        if (cedulaLabel) cedulaLabel.textContent = esEmpresa ? 'Cédula' : 'Cédula *';
    }

    if (tipoFiscal) tipoFiscal.addEventListener('change', actualizarTipoFiscal);
    actualizarTipoFiscal();
});
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
