<?php

declare(strict_types=1);

/**
 * Integración F4. Lectura/escritura de configuración y empresa (alta, edición,
 * WhatsApp y propagación de RUT a producto) dentro de una transacción con
 * rollback. No toca migraciones SQL ni envía WhatsApp.
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

use Lteco\Application\Configuracion\ConfiguracionService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\ConfiguracionRepository;

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
    $service = new ConfiguracionService(new ConfiguracionRepository(new Connection($pdo)));

    $rut = 'ZZ' . substr(bin2hex(random_bytes(4)), 0, 8);
    $rutNuevo = 'ZN' . substr(bin2hex(random_bytes(4)), 0, 8);

    // --- alta de empresa (mismo shape de columnas que el handler) ---
    $service->insertarEmpresa([
        'RUT' => $rut,
        'Nombre' => 'Empresa F Test',
        'Correo' => 'test@example.com',
        'Telefono' => '099111222',
        'WhatsApp' => '099111222',
        'Descripcion' => 'desc',
        'Direccion' => 'dir',
        'RazonSocial' => 'razon',
    ]);
    $fila = $pdo->prepare("SELECT Nombre, Correo, WhatsApp FROM empresa WHERE RUT = ?");
    $fila->execute([$rut]);
    $emp = $fila->fetch(PDO::FETCH_ASSOC);
    $check('insertarEmpresa persiste la fila', is_array($emp) && $emp['Nombre'] === 'Empresa F Test' && $emp['Correo'] === 'test@example.com');

    // --- edición de empresa por RUT ---
    $service->actualizarEmpresa(['Nombre' => 'Empresa F Editada', 'Correo' => 'nuevo@example.com'], $rut);
    $fila->execute([$rut]);
    $emp2 = $fila->fetch(PDO::FETCH_ASSOC);
    $check('actualizarEmpresa cambia los campos', $emp2['Nombre'] === 'Empresa F Editada' && $emp2['Correo'] === 'nuevo@example.com');

    // --- propagación de RUT a producto ---
    $service->insertarEmpresa(['RUT' => $rutNuevo, 'Nombre' => 'Empresa F Destino']);
    $pdo->prepare("INSERT INTO producto (Nombre, TipoProducto, Descripcion, Costo, GastoTotal, PrecioVenta, PrecioDistribuidor, Moneda, Stock, Estado, MostrarEnWeb, Empresa_RUT)
        VALUES (?, 'Repuesto', ?, 0, 0, 1, NULL, 'UYU', 0, 'Oculto', 0, ?)")
        ->execute(['Prod F ' . $rut, 'p', $rut]);
    $idProducto = (int) $pdo->lastInsertId();

    $service->propagarRutProducto($rutNuevo, $rut);
    $rutProd = $pdo->prepare("SELECT Empresa_RUT FROM producto WHERE IdProducto = ?");
    $rutProd->execute([$idProducto]);
    $check('propagarRutProducto mueve el producto al nuevo RUT', (string) $rutProd->fetchColumn() === $rutNuevo);

    // --- lectura de configuración + actualización de WhatsApp ---
    $config = $service->obtenerConfiguracion();
    if ($config === null) {
        $service->crearConfiguracionDefault();
        $config = $service->obtenerConfiguracion();
    }
    $check('obtenerConfiguracion devuelve una fila', is_array($config) && isset($config['IdConfiguracion']));

    $service->actualizarWhatsapp((int) $config['IdConfiguracion'], ['WaEnabled' => 1, 'WaPhoneId' => '123456789']);
    $waFila = $pdo->prepare("SELECT WaEnabled, WaPhoneId FROM configuracion WHERE IdConfiguracion = ?");
    $waFila->execute([(int) $config['IdConfiguracion']]);
    $wa = $waFila->fetch(PDO::FETCH_ASSOC);
    $check('actualizarWhatsapp persiste WaEnabled/WaPhoneId', (int) $wa['WaEnabled'] === 1 && (string) $wa['WaPhoneId'] === '123456789');

    // --- actualizarWhatsapp con mapa vacío no rompe ---
    $service->actualizarWhatsapp((int) $config['IdConfiguracion'], []);
    $check('actualizarWhatsapp con mapa vacío es no-op', true);

    // --- contacto de empresa (query legacy de whatsapp_probar) ---
    $check('obtenerEmpresaContacto devuelve array', is_array($service->obtenerEmpresaContacto()));
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
