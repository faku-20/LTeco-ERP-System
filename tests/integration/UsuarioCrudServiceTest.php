<?php

declare(strict_types=1);

/**
 * Integración F2. Alta, lectura, edición, cambio de clave, toggle y baja de
 * usuarios, dentro de una transacción con rollback. La criptografía no se
 * ejercita aquí: el hash de clave se pasa como string ya resuelto.
 */

spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Lteco\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $ruta = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require_once $ruta;
    }
});

use Lteco\Application\Usuario\UsuarioCrudService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\UsuarioRepository;

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$ok): void {
    if ($condicion) { $ok++; echo "  ✓ {$nombre}\n"; return; }
    $fallos[] = $nombre;
    echo "  ✗ {$nombre}\n";
};

$host = getenv('LTECO_DB_HOST') ?: '127.0.0.1';
$host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
$pdo = new PDO(
    'mysql:host=' . $host . ';dbname=' . (getenv('LTECO_DB_NAME') ?: 'lteco_db') . ';charset=utf8mb4',
    getenv('LTECO_DB_USER') ?: 'lteco_user',
    getenv('LTECO_DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $service = new UsuarioCrudService(new UsuarioRepository(new Connection($pdo)));

    $login = 'zz_f_' . bin2hex(random_bytes(4));

    $check('usuario disponible antes del alta', $service->usuarioDisponible($login));

    // --- alta ---
    $id = $service->crear([
        'nombre_completo' => 'Usuario F Test',
        'usuario' => $login,
        'clave_hash' => 'HASH_INICIAL',
        'rol' => 'Vendedor',
        'id_distribuidor' => null,
        'comision_pct' => 3.50,
        'comision_distribuidor_pct' => 1.25,
    ]);
    $check('crear devuelve IdUsuario > 0', $id > 0);
    $check('usuario ya no disponible tras el alta', !$service->usuarioDisponible($login));
    $check('disponibleExcepto se ignora a sí mismo', $service->usuarioDisponibleExcepto($login, $id));

    $edicion = $service->obtenerParaEdicion($id);
    $check('obtenerParaEdicion trae el usuario', is_array($edicion) && (int)$edicion['IdUsuario'] === $id);
    $check('alta persiste rol y comisiones', $edicion['Rol'] === 'Vendedor' && (float)$edicion['ComisionPct'] === 3.50 && (float)$edicion['ComisionDistribuidorPct'] === 1.25);
    $check('obtenerParaEdicion NO expone ClaveHash', !array_key_exists('ClaveHash', $edicion));

    // --- edición ---
    $service->actualizar($id, [
        'nombre_completo' => 'Usuario F Editado',
        'usuario' => $login,
        'rol' => 'Administrador',
        'id_distribuidor' => null,
        'comision_pct' => 5.00,
        'comision_distribuidor_pct' => 2.00,
    ]);
    $ed2 = $service->obtenerParaEdicion($id);
    $check('edición actualiza nombre y rol', $ed2['NombreCompleto'] === 'Usuario F Editado' && $ed2['Rol'] === 'Administrador');
    $check('edición actualiza comisiones', (float)$ed2['ComisionPct'] === 5.00 && (float)$ed2['ComisionDistribuidorPct'] === 2.00);

    // --- cambio de clave (solo persistencia del hash) ---
    $paraClave = $service->obtenerParaClave($id);
    $check('obtenerParaClave incluye ClaveHash', is_array($paraClave) && ($paraClave['ClaveHash'] ?? null) === 'HASH_INICIAL');
    $service->cambiarClave($id, 'HASH_NUEVO');
    $check('cambiarClave actualiza el hash', ($service->obtenerParaClave($id)['ClaveHash'] ?? null) === 'HASH_NUEVO');

    // --- toggle activo ---
    $paraToggle = $service->obtenerParaToggle($id);
    $check('usuario nace activo', (int)$paraToggle['Activo'] === 1);
    $service->actualizarActivo($id, 0);
    $check('toggle desactiva', (int)$service->obtenerParaToggle($id)['Activo'] === 0);

    // --- baja ---
    $service->eliminar($id);
    $check('eliminar borra el usuario', $service->obtenerParaEdicion($id) === null);
    $check('login vuelve a estar disponible tras la baja', $service->usuarioDisponible($login));
} finally {
    $pdo->rollBack();
    echo "\n[ROLLBACK ejecutado]\n";
}

if ($fallos === []) {
    echo "\nOK — {$ok} aserciones de integración pasaron.\n";
    exit(0);
}
echo "\nFALLÓ — " . count($fallos) . " aserciones.\n";
exit(1);
