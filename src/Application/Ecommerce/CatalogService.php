<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

use Lteco\Domain\Venta\ReglasComerciales;

final class CatalogService
{
    public function __construct(
        private CatalogDataSource $source,
        private PublicMediaUrl $media,
    ) {}

    /** @return array{data:list<array<string,mixed>>,meta:array<string,string|int>} */
    public function catalog(string $generatedAt): array
    {
        $snapshot = $this->source->catalogSnapshot();
        $units = $snapshot['units'];
        $imagesByVehicle = [];

        foreach ($snapshot['images'] as $image) {
            $url = $this->media->fromStoredPath((string) ($image['RutaImagen'] ?? ''));
            if ($url === null) {
                continue;
            }

            $imagesByVehicle[(string) $image['IdVehiculo']][] = [
                'url' => $url,
                'primary' => (bool) ($image['EsPrincipal'] ?? false),
                'order' => (int) ($image['OrdenImagen'] ?? 0),
            ];
        }

        $variants = [];

        foreach ($units as $unit) {
            $vehicleId = (string) $unit['IdVehiculo'];
            $gallery = $imagesByVehicle[$vehicleId] ?? [];
            if ($gallery === []) {
                error_log('Storefront catalog vehicle without gallery: '.substr(hash('sha256', $vehicleId), 0, 12));
            }

            $model = trim((string) $unit['Modelo']);
            $battery = $unit['CapacidadBateriaAh'] !== null
                ? (int) $unit['CapacidadBateriaAh']
                : null;
            $color = trim((string) ($unit['Color'] ?? ''));
            $currency = strtoupper(trim((string) $unit['Moneda']));
            $gross = self::decimal($unit['PrecioVenta']);
            $variantId = VariantIdentity::id($model, $battery, $color, $currency, $gross);
            $unitAvailable = (
                (string) ($unit['Estado'] ?? '') === 'Disponible'
                && (int) ($unit['Stock'] ?? 0) > 0
            ) ? 1 : 0;

            if (! isset($variants[$variantId])) {
                $vatRate = self::decimal($unit['TasaIVA'] ?? 22);
                $vatValue = ReglasComerciales::ivaIncluido((float) $gross, (float) $vatRate);
                $variants[$variantId] = [
                    'variant_id' => $variantId,
                    'slug' => trim((string) $unit['Slug']),
                    'model' => $model,
                    'battery_ah' => $battery,
                    'color' => $color,
                    'description' => trim((string) $unit['DescripcionWeb']),
                    'gallery' => $gallery,
                    '_order' => (int) ($unit['OrdenWeb'] ?? 0),
                    'availability' => [
                        'available' => $unitAvailable === 1,
                        'quantity' => $unitAvailable,
                    ],
                    'price' => [
                        'currency' => $currency,
                        'gross' => $gross,
                        'vat_rate' => $vatRate,
                        'vat_included' => self::decimal($vatValue),
                        'net' => self::decimal((float) $gross - $vatValue),
                    ],
                ];

                continue;
            }

            $variants[$variantId]['_order'] = min(
                (int) $variants[$variantId]['_order'],
                (int) ($unit['OrdenWeb'] ?? 0),
            );

            $knownUrls = array_column($variants[$variantId]['gallery'], 'url');
            foreach ($gallery as $image) {
                if (! in_array($image['url'], $knownUrls, true)) {
                    $variants[$variantId]['gallery'][] = $image;
                    $knownUrls[] = $image['url'];
                }
            }

            // Cada fila es una unidad física de la combinación modelo/color/batería.
            $variants[$variantId]['availability']['quantity'] += $unitAvailable;
            $variants[$variantId]['availability']['available'] =
                $variants[$variantId]['availability']['quantity'] > 0;
        }

        $data = array_values($variants);
        usort($data, static function (array $a, array $b): int {
            return [$a['_order'], $a['model'], $a['battery_ah'] ?? 0, $a['color'], $a['price']['gross']]
                <=> [$b['_order'], $b['model'], $b['battery_ah'] ?? 0, $b['color'], $b['price']['gross']];
        });

        foreach ($data as &$variant) {
            unset($variant['_order']);
        }
        unset($variant);

        $version = hash(
            'sha256',
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        foreach ($data as &$variant) {
            $variant['version'] = $version;
        }
        unset($variant);

        return [
            'data' => $data,
            'meta' => [
                'generated_at' => $generatedAt,
                'version' => $version,
                'count' => count($data),
            ],
        ];
    }

    private static function decimal(mixed $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
