<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Infrastructure\Db\Connection;
use PDO;

/**
 * Acceso a datos de configuración general y empresa (Ola F). SQL puro. No abre
 * transacciones: el handler conserva beginTransaction/commit/rollback y el
 * "ensure" de columnas/tabla de WhatsApp (DDL vía helpers). Preserva los
 * SELECT/UPDATE/INSERT legacy de configuracion/index.php y guardar.php.
 *
 * Las actualizaciones dinámicas reciben mapas asociativos cuyas CLAVES son
 * nombres de columna provenientes de literales del handler (mismo modelo de
 * confianza que el legacy, que armaba el SET con nombres fijos).
 */
final class ConfiguracionRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->pdo();
    }

    /**
     * Primera fila de configuración (o null).
     *
     * @return array<string,mixed>|null
     */
    public function obtenerConfiguracion(): ?array
    {
        $stmt = $this->pdo->query("SELECT * FROM configuracion ORDER BY IdConfiguracion ASC LIMIT 1");
        $fila = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $fila ?: null;
    }

    /**
     * Crea la fila de configuración por defecto (ComisionVendedor = 0).
     */
    public function crearConfiguracionDefault(): void
    {
        $this->pdo->prepare("INSERT INTO configuracion (ComisionVendedor) VALUES (?)")->execute([0.0]);
    }

    /**
     * Primera fila de empresa (o null).
     *
     * @return array<string,mixed>|null
     */
    public function obtenerEmpresa(): ?array
    {
        $stmt = $this->pdo->query("SELECT * FROM empresa ORDER BY Nombre ASC, RUT ASC LIMIT 1");
        $fila = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $fila ?: null;
    }

    /**
     * Contacto de la empresa para la prueba de WhatsApp (WhatsApp/Telefono).
     * Conserva la query legacy de whatsapp_probar.php (orden solo por Nombre).
     *
     * @return array<string,mixed>|null
     */
    public function obtenerEmpresaContacto(): ?array
    {
        $stmt = $this->pdo->query("SELECT WhatsApp, Telefono FROM empresa ORDER BY Nombre ASC LIMIT 1");
        $fila = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $fila ?: null;
    }

    /**
     * Actualiza las columnas de WhatsApp presentes en $campos (clave = columna).
     * No hace nada si el mapa está vacío.
     *
     * @param array<string,mixed> $campos
     */
    public function actualizarWhatsapp(int $idConfiguracion, array $campos): void
    {
        if (!$campos) {
            return;
        }
        $sets = [];
        $params = [];
        foreach ($campos as $columna => $valor) {
            $sets[] = $columna . ' = ?';
            $params[] = $valor;
        }
        $params[] = $idConfiguracion;
        $this->pdo->prepare('UPDATE configuracion SET ' . implode(', ', $sets) . ' WHERE IdConfiguracion = ?')->execute($params);
    }

    /**
     * Actualiza la empresa identificada por su RUT actual con el mapa de
     * columnas dado (clave = columna).
     *
     * @param array<string,mixed> $columnas
     */
    public function actualizarEmpresa(array $columnas, string $rutActual): void
    {
        $sets = [];
        $params = [];
        foreach ($columnas as $columna => $valor) {
            $sets[] = $columna . ' = ?';
            $params[] = $valor;
        }
        $params[] = $rutActual;
        $this->pdo->prepare('UPDATE empresa SET ' . implode(', ', $sets) . ' WHERE RUT = ?')->execute($params);
    }

    /**
     * Inserta una empresa con el mapa de columnas dado (clave = columna).
     *
     * @param array<string,mixed> $columnas
     */
    public function insertarEmpresa(array $columnas): void
    {
        $this->pdo->prepare(
            'INSERT INTO empresa (' . implode(', ', array_keys($columnas)) . ') VALUES (' . implode(', ', array_fill(0, count($columnas), '?')) . ')'
        )->execute(array_values($columnas));
    }

    /**
     * Propaga el cambio de RUT de la empresa a los productos asociados.
     */
    public function propagarRutProducto(string $rutNuevo, string $rutAnterior): void
    {
        $this->pdo->prepare('UPDATE producto SET Empresa_RUT = ? WHERE Empresa_RUT = ?')->execute([$rutNuevo, $rutAnterior]);
    }
}
