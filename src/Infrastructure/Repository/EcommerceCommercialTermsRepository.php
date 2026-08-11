<?php

declare(strict_types=1);

namespace Lteco\Infrastructure\Repository;

use Lteco\Domain\Venta\ConfiguracionComercial;
use Lteco\Infrastructure\Db\Connection;
use PDO;

final class EcommerceCommercialTermsRepository
{
    private PDO $pdo;

    public function __construct(Connection $connection) { $this->pdo = $connection->pdo(); }

    /** @return array{config:array<string,float>,effective_at:string} */
    public function current(float $defaultVat): array
    {
        $stmt = $this->pdo->query('SELECT DescuentoContado,RecargoTarjeta,ComisionDistribuidor,
            ComisionVendedor,TasaIVA,StorefrontUpdatedAt FROM configuracion ORDER BY IdConfiguracion DESC LIMIT 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        $effective = $row && !empty($row['StorefrontUpdatedAt'])
            ? gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $row['StorefrontUpdatedAt']))
            : '1970-01-01T00:00:00Z';
        return ['config' => ConfiguracionComercial::normalizar($row ?: null, $defaultVat), 'effective_at' => $effective];
    }
}
