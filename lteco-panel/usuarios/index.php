<?php
$pageTitle = "Usuarios | Ltecobike";

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
requiereGestionUsuarios();
require_once __DIR__ . "/../includes/helpers.php";

$usuarios = (new \Lteco\Application\Usuario\UsuarioConsultaService(
    new \Lteco\Infrastructure\Repository\UsuarioRepository(
        new \Lteco\Infrastructure\Db\Connection($pdo)
    )
))->listar();
$rolesPermitidos = rolesQuePuedeCrearUsuarioActual();

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <div>
            <h1>Usuarios</h1>
            <p class="subtle">
                Superadmin administra la jerarquía. Administrador solo gestiona vendedores. Vendedor no accede a usuarios.
            </p>
        </div>
        <div class="actions-row">
            <?php if ($rolesPermitidos): ?>
                <a class="btn" href="<?= panelBaseUrl('usuarios/crear.php') ?>">+ Nuevo usuario</a>
            <?php endif; ?>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <section class="v4-card">
        <div class="list-head-v4">
            <div>
                <h2>Usuarios del sistema</h2>
                <p><strong>Superadmin:</strong> administra sistema y jerarquía &mdash; <strong>Administrador:</strong> administra el negocio &mdash; <strong>Vendedor:</strong> opera el sistema.</p>
            </div>
            <div class="result-pill-v4"><?= count($usuarios) ?> usuarios</div>
        </div>
    </section>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Comisión directa</th>
                    <th>Comisión distribuidor</th>
                    <th>Fecha alta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($usuarios): ?>
                    <?php foreach ($usuarios as $u): ?>
                        <?php
                            $u['Rol'] = rolNormalizado($u['Rol'] ?? ROL_VENDEDOR);
                            $puedeClave   = puedeCambiarClaveUsuario($u);
                            $puedeToggle  = puedeActivarDesactivarUsuario($u);
                            $puedeEditar  = puedeCambiarRolUsuario($u);
                            $puedeEliminar = esSuperadmin()
                                && (int)$u['IdUsuario'] !== (int)(usuarioActual()['IdUsuario'] ?? 0)
                                && $u['Rol'] !== ROL_SUPERADMIN;
                        ?>
                        <tr>
                            <td><?= (int)$u['IdUsuario'] ?></td>
                            <td><?= h($u['NombreCompleto'], '') ?></td>
                            <td><?= h($u['Usuario'], '') ?></td>
                            <td>
                                <?php
                                $rolClass = match($u['Rol'] ?? '') {
                                    'Superadmin'    => 'badge-superadmin',
                                    'Administrador' => 'badge-admin',
                                    'Vendedor'      => 'badge-vendedor',
                                    'Distribuidor'  => 'badge-distribuidor',
                                    default         => 'badge-default',
                                };
                                ?>
                                <span class="badge <?= $rolClass ?>"><?= h($u['Rol'], '') ?></span>
                            </td>
                            <td>
                                <?php if ((int)$u['Activo'] === 1): ?>
                                    <span class="badge badge-disponible">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-vendido">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format((float)($u['ComisionPct'] ?? 0), 2, ',', '.') ?>%</td>
                            <td><?= number_format((float)($u['ComisionDistribuidorPct'] ?? 0), 2, ',', '.') ?>%</td>
                            <td><?= h($u['FechaAlta'] ?? '-') ?></td>
                            <td>
                                <div class="user-table-actions">
                                    <?php if ($puedeEditar): ?>
                                        <a class="btn-secondary user-action-edit" href="<?= panelBaseUrl('usuarios/editar.php?id=' . urlencode((string)$u['IdUsuario'])) ?>">
                                            Editar usuario
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($puedeClave): ?>
                                        <a class="btn-secondary user-action-key" href="<?= panelBaseUrl('usuarios/cambiar_clave.php?id=' . urlencode((string)$u['IdUsuario'])) ?>">
                                            Cambiar clave
                                        </a>
                                    <?php endif; ?>

                                    <?php $puedeMfa = usuarioRequiereMfa($u) && (esSuperadmin() || ((int)$u['IdUsuario'] === (int)(usuarioActual()['IdUsuario'] ?? 0) && esAdministrador())); ?>
                                    <?php if ($puedeMfa): ?>
                                        <a class="btn-secondary user-action-key" href="<?= panelBaseUrl('usuarios/mfa.php?id=' . urlencode((string)$u['IdUsuario'])) ?>">
                                            MFA <?= !empty($u['mfa_enabled']) ? 'activo' : 'configurar' ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($puedeEliminar): ?>
                                        <form method="POST" action="<?= panelBaseUrl('usuarios/eliminar.php') ?>" class="user-action-form user-action-delete" data-confirm="¿Eliminar al usuario <?= h($u['Usuario']) ?>? Esta acción no se puede deshacer.">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="id" value="<?= (int)$u['IdUsuario'] ?>">
                                            <button type="submit" class="btn-danger-outline">
                                                Eliminar
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($puedeToggle): ?>
                                        <form method="POST" action="<?= panelBaseUrl('usuarios/toggle_activo.php') ?>" class="user-action-form user-action-toggle" data-confirm="¿Cambiar estado de este usuario?">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="id" value="<?= (int)$u['IdUsuario'] ?>">
                                            <button type="submit" class="btn-secondary">
                                                <?= (int)$u['Activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!$puedeEditar && !$puedeClave && !$puedeToggle && !$puedeEliminar): ?>
                                        <span class="subtle">Sin acciones disponibles</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">No hay usuarios creados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
