<?php

declare(strict_types=1);

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

use Lteco\Application\Repuesto\RepuestoCrudService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\RepuestoCrudRepository;

$fallos = [];
$ok = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$ok): void {
    if ($condicion) {
        $ok++;
        echo "  ✓ {$nombre}\n";
        return;
    }
    $fallos[] = $nombre;
    echo "  ✗ {$nombre}\n";
};

$host = getenv('LTECO_DB_HOST') ?: '127.0.0.1';
$host = ($host === 'host.docker.internal' && gethostbyname($host) === $host) ? '127.0.0.1' : $host;
$pdo = new PDO(
    'mysql:host=' . $host . ';dbname=' . (getenv('LTECO_DB_NAME') ?: 'lteco_db') . ';charset=utf8mb4',
    getenv('LTECO_DB_USER') ?: 'lteco_user',
    getenv('LTECO_DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $service = new RepuestoCrudService(
        new RepuestoCrudRepository(new Connection($pdo))
    );

    // RUT de empresa válido reutilizado de un producto existente (FK) o null.
    $empresaRut = $pdo->query("SELECT Empresa_RUT FROM producto WHERE Empresa_RUT IS NOT NULL LIMIT 1")->fetchColumn();
    $empresaRut = $empresaRut !== false ? (string) $empresaRut : null;
    // Importación activa existente para probar el tipo de cambio, si hay.
    $impRow = $pdo->query("SELECT Numero, TipoCambioUSD FROM importacion WHERE Activa = 1 ORDER BY Numero ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // --- validación pura ---
    $erroresVacio = $service->validar([
        'nombre' => '',
        'precio_venta' => 0.0,
        'precio_distribuidor' => null,
        'stock' => 0,
        'estado' => 'Disponible',
    ]);
    $check('valida nombre obligatorio', in_array('El nombre es obligatorio.', $erroresVacio, true));
    $check('valida precio venta > 0', in_array('El precio de venta debe ser mayor a 0.', $erroresVacio, true));

    $erroresPrecioCeroLegacy = $service->validar([
        'nombre' => 'Repuesto histórico',
        'precio_venta' => 0.0,
        'precio_distribuidor' => null,
        'stock' => 5,
        'estado' => 'Disponible',
        'permitir_precio_venta_cero' => true,
    ]);
    $check('editar permite preservar precio venta 0 histórico', !in_array('El precio de venta debe ser mayor a 0.', $erroresPrecioCeroLegacy, true));

    $erroresDist = $service->validar([
        'nombre' => 'X',
        'precio_venta' => 100.0,
        'precio_distribuidor' => 150.0,
        'stock' => 5,
        'estado' => 'Disponible',
    ]);
    $check('valida distribuidor no mayor a venta', in_array('El precio distribuidor no debería ser mayor al precio de venta.', $erroresDist, true));

    $erroresDispSinStock = $service->validar([
        'nombre' => 'X',
        'precio_venta' => 100.0,
        'precio_distribuidor' => null,
        'stock' => 0,
        'estado' => 'Disponible',
    ]);
    $check('valida disponible requiere stock', in_array('Un repuesto disponible debe tener stock mayor a 0.', $erroresDispSinStock, true));

    $valido = $service->validar([
        'nombre' => 'Repuesto Test',
        'precio_venta' => 100.0,
        'precio_distribuidor' => 80.0,
        'stock' => 5,
        'estado' => 'Disponible',
    ]);
    $check('formulario válido sin errores', $valido === []);

    // --- crear ---
    $datosCrear = [
        'nombre' => 'Repuesto D1 Test',
        'descripcion' => 'Descripción de prueba',
        'costo' => 10.5,
        'gasto_total' => 2.0,
        'precio_venta' => 100.0,
        'precio_distribuidor' => 80.0,
        'moneda' => 'UYU',
        'stock' => 7,
        'estado' => 'Disponible',
        'numero_importacion' => null,
        'empresa_rut' => $empresaRut,
    ];
    // Lectura directa de producto+repuesto (columnas fuera del set legacy de obtener()).
    $fila = static function (int $idProducto) use ($pdo): ?array {
        $stmt = $pdo->prepare("
            SELECT p.TipoProducto, p.MostrarEnWeb, r.NumeroImportacion, r.TipoCambioImportacion
            FROM repuesto r INNER JOIN producto p ON p.IdProducto = r.IdProducto
            WHERE p.IdProducto = ? LIMIT 1
        ");
        $stmt->execute([$idProducto]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    };

    $idProducto = $service->crear($datosCrear);
    $check('crear devuelve id de producto', $idProducto > 0);

    $creado = $service->obtener($idProducto);
    $filaCreado = $fila($idProducto);
    $check('crear persiste como Repuesto', $filaCreado !== null && $filaCreado['TipoProducto'] === 'Repuesto');
    $check('crear persiste nombre', $creado !== null && $creado['Nombre'] === 'Repuesto D1 Test');
    $check('crear persiste stock', $creado !== null && (int) $creado['Stock'] === 7);
    $check('crear persiste precio distribuidor', $creado !== null && (float) $creado['PrecioDistribuidor'] === 80.0);
    $check('crear deja MostrarEnWeb=0', $filaCreado !== null && (int) $filaCreado['MostrarEnWeb'] === 0);
    $check('crear sin importación deja NumeroImportacion null', $filaCreado !== null && $filaCreado['NumeroImportacion'] === null);
    $check('obtener no expone NumeroImportacion (set legacy)', $creado !== null && !array_key_exists('NumeroImportacion', $creado));

    // --- editar ---
    $datosEditar = [
        'nombre' => 'Repuesto D1 Editado',
        'descripcion' => 'Otra descripción',
        'costo' => 12.0,
        'gasto_total' => 3.0,
        'precio_venta' => 120.0,
        'precio_distribuidor' => null,
        'moneda' => 'USD',
        'stock' => 0,
        'estado' => 'Sin stock',
        'numero_importacion' => $impRow ? (int) $impRow['Numero'] : null,
    ];
    $service->editar($idProducto, $datosEditar);
    $editado = $service->obtener($idProducto);
    $filaEditado = $fila($idProducto);
    $check('editar persiste nombre', $editado !== null && $editado['Nombre'] === 'Repuesto D1 Editado');
    $check('editar persiste moneda', $editado !== null && $editado['Moneda'] === 'USD');
    $check('editar persiste estado', $editado !== null && $editado['Estado'] === 'Sin stock');
    $check('editar limpia precio distribuidor', $editado !== null && $editado['PrecioDistribuidor'] === null);
    if ($impRow) {
        $check('editar resuelve tipo de cambio de importación', $filaEditado !== null && (float) $filaEditado['TipoCambioImportacion'] === (float) $impRow['TipoCambioUSD']);
        $check('editar persiste numero de importación', $filaEditado !== null && (int) $filaEditado['NumeroImportacion'] === (int) $impRow['Numero']);
    }

    // --- ocultar ---
    $idRepuesto = (int) $editado['IdRepuesto'];
    $paraOcultar = $service->repuestoParaOcultar($idRepuesto);
    $check('repuestoParaOcultar encuentra el repuesto', $paraOcultar !== null && (int) $paraOcultar['IdProducto'] === $idProducto);
    $service->ocultar((int) $paraOcultar['IdProducto']);
    $oculto = $service->obtener($idProducto);
    $check('ocultar deja Estado=Oculto', $oculto !== null && $oculto['Estado'] === 'Oculto');
    $check('ocultar deja MostrarEnWeb=0', $oculto !== null && (int) $oculto['MostrarEnWeb'] === 0);

    $check('repuestoParaOcultar devuelve null si no existe', $service->repuestoParaOcultar(0) === null);
    $check('obtener devuelve null si no existe', $service->obtener(0) === null);
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
