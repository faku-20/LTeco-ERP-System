<?php

declare(strict_types=1);

/**
 * Integración F3. Persistencia de MFA (activar/desactivar/regenerar) dentro de
 * una transacción con rollback. NO ejercita criptografía real: el secreto
 * protegido y los recovery codes hasheados se pasan como strings de prueba; el
 * test solo verifica que el SQL persiste/limpia las columnas correctas.
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
use Lteco\Application\Usuario\UsuarioMfaService;
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
    $conn = new Connection($pdo);
    $crud = new UsuarioCrudService(new UsuarioRepository($conn));
    $mfa = new UsuarioMfaService(new UsuarioRepository($conn));

    $login = 'zz_fmfa_' . bin2hex(random_bytes(4));
    $id = $crud->crear([
        'nombre_completo' => 'MFA Test', 'usuario' => $login, 'clave_hash' => 'H',
        'rol' => 'Administrador', 'id_distribuidor' => null, 'comision_pct' => 0.0, 'comision_distribuidor_pct' => 0.0,
    ]);

    $base = $mfa->obtener($id);
    $check('usuario nace sin MFA', (int)$base['mfa_enabled'] === 0 && $base['mfa_secret'] === null && $base['mfa_recovery_codes'] === null);

    // --- activar (valores ya protegidos/hasheados, simulados) ---
    $mfa->activar($id, 'enc:v1:secreto-protegido', 'hash-recovery-1');
    $act = $mfa->obtener($id);
    $check('activar enciende mfa_enabled', (int)$act['mfa_enabled'] === 1);
    $check('activar persiste secret y recovery', $act['mfa_secret'] === 'enc:v1:secreto-protegido' && $act['mfa_recovery_codes'] === 'hash-recovery-1');

    // --- regenerar recovery (solo cambia recovery codes) ---
    $mfa->regenerarRecovery($id, 'hash-recovery-2');
    $reg = $mfa->obtener($id);
    $check('regenerar cambia recovery codes', $reg['mfa_recovery_codes'] === 'hash-recovery-2');
    $check('regenerar conserva mfa_enabled y secret', (int)$reg['mfa_enabled'] === 1 && $reg['mfa_secret'] === 'enc:v1:secreto-protegido');

    // --- desactivar (apaga y limpia) ---
    $mfa->desactivar($id);
    $des = $mfa->obtener($id);
    $check('desactivar apaga y limpia secret/recovery', (int)$des['mfa_enabled'] === 0 && $des['mfa_secret'] === null && $des['mfa_recovery_codes'] === null);
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
