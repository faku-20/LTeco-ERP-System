<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

use Lteco\Domain\Venta\ConfiguracionComercial;

final class CommercialTermsService
{
    /**
     * @param array<string,float> $config
     * @param array<string,list<int>> $installmentsByBrand
     * @return array{data:array<string,mixed>}
     */
    public function terms(array $config, array $installmentsByBrand, string $effectiveAt): array
    {
        $config = ConfiguracionComercial::normalizar($config, 22.0);
        $cards = [];
        foreach ($installmentsByBrand as $brand => $installments) {
            $values = array_values(array_unique(array_map('intval', $installments)));
            sort($values);
            $cards[] = ['brand' => $brand, 'type' => 'credit', 'installments' => $values];
        }
        usort($cards, static fn(array $a, array $b): int => $a['brand'] <=> $b['brand']);
        $data = [
            'currency' => 'UYU',
            'vat_rate' => self::decimal($config['TasaIVA']),
            'cash_discount_pct' => self::decimal($config['DescuentoContado'] ?? 0),
            'cards' => $cards,
            'effective_at' => $effectiveAt,
        ];
        $data['version'] = hash('sha256', (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return ['data' => $data];
    }

    private static function decimal(mixed $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
