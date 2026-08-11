<?php

declare(strict_types=1);

/**
 * Integración E3. Verifica el listado de importaciones con conteo de vehículos
 * y el orden por Numero DESC, dentro de una transacción con rollback.
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

use Lteco\Application\Importacion\ImportacionConsultaService;
use Lteco\Infrastructure\Db\Connection;
use Lteco\Infrastructure\Repository\ImportacionConsultaRepository;

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
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

echo "Abriendo transacción (ROLLBACK al final).\n\n";
$pdo->beginTransaction();
try {
    $service = new ImportacionConsultaService(new ImportacionConsultaRepository(new Connection($pdo)));

    // Dos importaciones nuevas por encima del máximo Numero existente:
    // así quedan primeras en el orden DESC y aisladas de los datos reales.
    $maxNum = (int) $pdo->query("SELECT COALESCE(MAX(Numero), 0) FROM importacion")->fetchColumn();
    $n1 = $maxNum + 1000;
    $n2 = $maxNum + 1001;

    $ins = $pdo->prepare("INSERT INTO importacion (Numero, TipoCambioUSD, Fecha, Descripcion, Activa) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$n1, 40.00, '2099-01-01', 'E3 menor', 1]);
    $ins->execute([$n2, 41.00, '2099-01-02', 'E3 mayor', 0]);

    $lista = $service->listar();
    $check('listar devuelve filas', is_array($lista) && count($lista) >= 2);

    // Las dos nuevas son las de mayor Numero → encabezan la lista (DESC).
    $check('orden DESC: la mayor (n2) va primera', (int) $lista[0]['Numero'] === $n2);
    $check('orden DESC: la menor (n1) va segunda', (int) $lista[1]['Numero'] === $n1);

    $check('importación nueva sin vehículos cuenta 0', (int) $lista[0]['TotalVehiculos'] === 0 && (int) $lista[1]['TotalVehiculos'] === 0);
    $check('SELECT i.* expone columnas base', array_key_exists('IdImportacion', $lista[0]) && array_key_exists('TipoCambioUSD', $lista[0]) && array_key_exists('Activa', $lista[0]));
    $check('conserva Activa tal cual (n2 inactiva)', (int) $lista[0]['Activa'] === 0 && (int) $lista[1]['Activa'] === 1);

    // Path del COUNT contra datos reales: si existe alguna importación con
    // vehículos, el listado debe reportar el mismo total que un COUNT directo.
    $conVehiculos = $pdo->query("
        SELECT i.Numero AS Numero, COUNT(v.IdVehiculo) AS c
        FROM importacion i
        LEFT JOIN vehiculo v ON v.NumeroImportacion = i.Numero
        GROUP BY i.IdImportacion
        HAVING c > 0
        ORDER BY i.Numero DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if ($conVehiculos) {
        $numero = (int) $conVehiculos['Numero'];
        $esperado = (int) $conVehiculos['c'];
        $fila = null;
        foreach ($lista as $row) {
            if ((int) $row['Numero'] === $numero) {
                $fila = $row;
                break;
            }
        }
        $check('COUNT de vehículos coincide con COUNT directo', $fila !== null && (int) $fila['TotalVehiculos'] === $esperado);
    } else {
        echo "  (sin importaciones con vehículos en la DB: se omite el cruce del COUNT)\n";
    }
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
