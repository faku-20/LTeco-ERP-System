<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Lteco\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $path = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use Lteco\Domain\Venta\ReglasAnulacion;
use Lteco\Domain\Venta\ResultadoAnulacion;

$fallos = [];
$pasaron = 0;
$check = static function (string $nombre, bool $condicion) use (&$fallos, &$pasaron): void {
    if ($condicion) {
        $pasaron++;
        echo "  OK {$nombre}\n";
        return;
    }

    $fallos[] = $nombre;
    echo "  FAIL {$nombre}\n";
};

$check('Moto es vehículo sin importar mayúsculas', ReglasAnulacion::esVehiculo(' moto '));
$check('Vehiculo es vehículo', ReglasAnulacion::esVehiculo('VEHICULO'));
$check('Repuesto no es vehículo', !ReglasAnulacion::esVehiculo('Repuesto'));
$check('columna no ENUM acepta cualquier estado', ReglasAnulacion::estadoPermitido([
    'COLUMN_TYPE' => 'varchar(30)',
], 'Anulada'));
$check('ENUM acepta estado presente', ReglasAnulacion::estadoPermitido([
    'COLUMN_TYPE' => "enum('Pendiente','Anulada')",
], 'Anulada'));
$check('ENUM rechaza estado ausente', !ReglasAnulacion::estadoPermitido([
    'COLUMN_TYPE' => "enum('Pendiente','Pagada')",
], 'Anulada'));
$check('ENUM interpreta escapes', ReglasAnulacion::estadoPermitido([
    'COLUMN_TYPE' => "enum('O\\'Brien','Anulada')",
], "O'Brien"));
$check('gasto prefiere Inactivo', ReglasAnulacion::estadoGastoAnulado([
    'COLUMN_TYPE' => "enum('Activo','Inactivo','Anulado')",
]) === 'Inactivo');
$check('gasto cae a Anulado', ReglasAnulacion::estadoGastoAnulado([
    'COLUMN_TYPE' => "enum('Activo','Anulado')",
]) === 'Anulado');

try {
    ReglasAnulacion::requerirEstado(
        ['COLUMN_TYPE' => "enum('Pendiente','Pagada')"],
        'distribuidor_comision',
        'Anulada'
    );
    $check('estado faltante lanza excepción', false);
} catch (RuntimeException $e) {
    $check(
        'mensaje de estado faltante se preserva',
        $e->getMessage() ===
            "La tabla distribuidor_comision no permite Estado='Anulada'. No se aplicaron cambios parciales."
    );
}

$resultado = new ResultadoAnulacion(3, 2, 1, 'Anulado', 4, 5);
$check('resultado conserva conteos de auditoría', $resultado->paraAuditoria() === [
    'productos_revertidos' => 3,
    'comisiones_anuladas' => 2,
    'gastos_comision_inactivados' => 1,
    'estado_gasto_usado' => 'Anulado',
    'garantias_anuladas' => 4,
    'services_cancelados' => 5,
]);

echo "\n";
if ($fallos === []) {
    echo "OK - {$pasaron} aserciones de anulación pasaron.\n";
    exit(0);
}

echo 'FALLO - ' . count($fallos) . " aserciones:\n";
foreach ($fallos as $fallo) {
    echo " - {$fallo}\n";
}
exit(1);
