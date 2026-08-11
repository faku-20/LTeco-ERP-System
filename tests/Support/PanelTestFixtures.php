<?php

declare(strict_types=1);

final class PanelTestFixtures
{
    public function __construct(private readonly PDO $pdo) {}

    public function ensureEmpresa(): string
    {
        $rut = '999999999999';
        $exists = $this->pdo->prepare('SELECT RUT FROM empresa WHERE RUT = ? LIMIT 1');
        $exists->execute([$rut]);
        if ($exists->fetchColumn()) {
            return $rut;
        }

        $this->pdo->prepare('INSERT INTO empresa (RUT, Nombre, Telefono, Correo) VALUES (?, ?, ?, ?)')
            ->execute([$rut, 'LTECO TEST', '092000000', 'empresa-b1@example.test']);

        return $rut;
    }

    public function usuario(string $rol, string $suffix): int
    {
        $usuario = 'b1_' . strtolower($rol) . '_' . $suffix;
        $stmt = $this->pdo->prepare('INSERT INTO usuario (Usuario, ClaveHash, Rol, NombreCompleto, Activo) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$usuario, password_hash('Test123456789', PASSWORD_DEFAULT), $rol, 'B1 ' . $rol]);

        return (int) $this->pdo->lastInsertId();
    }

    public function cliente(string $suffix): int
    {
        $email = 'cliente-b1-' . $suffix . '@example.test';
        $this->pdo->prepare('INSERT INTO cliente (NombreApellido, TipoFiscal, Telefono, Correo, Cedula) VALUES (?, ?, ?, ?, ?)')
            ->execute(['Cliente B1 ' . $suffix, 'Consumidor final', '092000001', $email, 'B1' . substr(hash('crc32b', $suffix), 0, 6)]);

        return (int) $this->pdo->lastInsertId();
    }

    public function vehiculo(string $suffix, int $stock = 1): array
    {
        $rut = $this->ensureEmpresa();
        $slug = 'b1-vehiculo-' . $suffix;
        $this->pdo->prepare("
            INSERT INTO producto
                (Nombre, Slug, TipoProducto, Costo, GastoTotal, PrecioVenta, Stock, Estado, Empresa_RUT, Moneda, MostrarEnWeb)
            VALUES (?, ?, 'Moto', 100.00, 0.00, 1000.00, ?, 'Disponible', ?, 'UYU', 1)
        ")->execute(['B1 Vehiculo ' . $suffix, $slug, $stock, $rut]);
        $idProducto = (int) $this->pdo->lastInsertId();
        $idVehiculo = 'B1' . strtoupper(substr(hash('crc32b', $suffix . microtime(true)), 0, 8));
        $this->pdo->prepare('INSERT INTO vehiculo (IdVehiculo, IdProducto, Modelo, NumeroMotor, Color) VALUES (?, ?, ?, ?, ?)')
            ->execute([$idVehiculo, $idProducto, 'B1 Test', 'MOTOR-' . $idVehiculo, 'Verde']);

        return ['idProducto' => $idProducto, 'idVehiculo' => $idVehiculo];
    }

    public function repuesto(string $suffix, int $stock): array
    {
        $rut = $this->ensureEmpresa();
        $slug = 'b1-repuesto-' . $suffix;
        $estado = $stock > 0 ? 'Disponible' : 'Sin stock';
        $this->pdo->prepare("
            INSERT INTO producto
                (Nombre, Slug, TipoProducto, Costo, GastoTotal, PrecioVenta, Stock, Estado, Empresa_RUT, Moneda)
            VALUES (?, ?, 'Repuesto', 10.00, 0.00, 100.00, ?, ?, ?, 'UYU')
        ")->execute(['B1 Repuesto ' . $suffix, $slug, $stock, $estado, $rut]);
        $idProducto = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO repuesto (IdProducto, NombreInterno) VALUES (?, ?)')
            ->execute([$idProducto, 'B1 Repuesto ' . $suffix]);

        return ['idProducto' => $idProducto, 'idRepuesto' => (int) $this->pdo->lastInsertId()];
    }

    public function distribuidor(string $suffix): int
    {
        $this->pdo->prepare('INSERT INTO distribuidor (Nombre, Telefono, Email, Activo) VALUES (?, ?, ?, 1)')
            ->execute(['Distribuidor B1 ' . $suffix, '092000002', 'dist-b1-' . $suffix . '@example.test']);

        return (int) $this->pdo->lastInsertId();
    }
}
