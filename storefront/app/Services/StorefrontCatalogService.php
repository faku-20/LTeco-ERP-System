<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CatalogoMoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class StorefrontCatalogService
{
    public function __construct(private readonly PanelCatalogService $panel) {}

    /** @return array{models: Collection<int, object>, realtime: bool} */
    public function load(): array
    {
        try {
            $rows = $this->panel->allVariants();

            // Una respuesta válida pero vacía significa que el panel no tiene
            // modelos publicados. No se debe reponer un catálogo viejo.
            return [
                'models' => $this->buildModels(
                    $rows->map(fn (array $row): array => $this->normalizePanelRow($row)),
                ),
                'realtime' => true,
            ];
        } catch (Throwable $exception) {
            if (! app()->environment('testing')) Log::warning('No fue posible consultar el catálogo del panel.', ['exception' => $exception::class]);
        }

        try {
            $legacy = CatalogoMoto::query()->orderByDesc('destacado')->orderBy('orden')->orderBy('nombre')->get();
        } catch (Throwable $exception) {
            $legacy = collect();
            if (! app()->environment('testing')) Log::warning('No fue posible consultar el catálogo local.', ['exception' => $exception::class]);
        }

        $models = $this->buildModels($legacy->map(fn (CatalogoMoto $row): array => $this->normalizeLegacyRow($row)));
        if ($models->isEmpty()) $models = $this->buildEditorialFallback();
        return ['models' => $models, 'realtime' => false];
    }

    public function find(string $slug): ?object
    {
        return $this->load()['models']->firstWhere('slug', $slug);
    }

    /** @param Collection<int,array<string,mixed>> $rows */
    private function buildModels(Collection $rows): Collection
    {
        $usedSlugs = [];
        return $rows->filter(function (array $row): bool {
            $model = trim((string) ($row['model'] ?? $row['name'] ?? ''));
            $price = (float) (
                is_array($row['price'] ?? null)
                    ? ($row['price']['gross'] ?? 0)
                    : ($row['price'] ?? 0)
            );
            return $model !== '' && $price > 0;
        })->groupBy(fn (array $row): string => $this->groupKey($row))->map(function (Collection $matches, string $groupKey) use (&$usedSlugs): object {
            $first = $matches->first();
            $baseSlug = trim((string) ($first['slug'] ?? ''));
            $slug = Str::slug($baseSlug !== '' ? $baseSlug : (string) ($first['model'] ?? ''));
            if ($slug === '') $slug = 'modelo-'.substr(hash('sha256', $groupKey), 0, 12);
            if (isset($usedSlugs[$slug])) $slug .= '-'.substr(hash('sha256', $groupKey), 0, 8);
            $usedSlugs[$slug] = true;

            $variants = $matches->filter(fn (array $row): bool => isset($row['variant_id']))->values();
            $prices = $matches
                ->pluck('price')
                ->map(fn (mixed $value): float => (float) (
                    is_array($value)
                        ? ($value['gross'] ?? 0)
                        : $value
                ))
                ->filter(fn (float $value): bool => $value > 0);
            $batteries = $matches->pluck('battery_ah')->filter(fn (mixed $value): bool => is_numeric($value))->map(fn (mixed $value): int => (int) $value)->unique()->sort()->values()->all();
            $colors = $matches->pluck('color')->filter(fn (mixed $value): bool => trim((string) $value) !== '')->map(fn (mixed $value): string => trim((string) $value))->unique()->values()->all();
            $available = $matches->contains(fn (array $row): bool => (int) ($row['availability']['quantity'] ?? 0) > 0);
            $images = $matches->flatMap(fn (array $row): array => $row['gallery'] ?? [])->map(fn (mixed $image): string => is_array($image) ? (string) ($image['url'] ?? '') : (string) $image)->filter()->unique()->values()->all();
            if ($images === []) $images = ['images/editorial/hero-principal.webp'];
            $power = preg_match('/(\d+)\s*W/i', (string) ($first['model'] ?? ''), $powerMatch) === 1 ? (int) $powerMatch[1] : null;
            $whatsapp = preg_replace('/\D+/', '', (string) config('storefront.whatsapp_number', ''));

            return (object) [
                'slug' => $slug,
                'nombre' => trim((string) ($first['model'] ?? $first['name'] ?? 'Modelo')),
                'potencia_w' => $power,
                'descripcion' => trim((string) ($matches->pluck('description')->first(fn ($value) => trim((string) $value) !== '') ?? 'Conocé este modelo eléctrico LTecobike.')),
                'imagenes' => $images,
                'precio' => $prices->min(),
                'moneda' => (string) ($matches->pluck('currency')->filter()->first() ?: 'UYU'),
                'tiene_precio' => $prices->isNotEmpty(),
                'colores' => $colors,
                'bateria_ah' => $batteries === [] ? null : max($batteries),
                'opciones_bateria' => $batteries,
                'disponible' => $available,
                'agotado' => ! $available,
                'tiene_datos_reales' => true,
                'variantes' => $variants->values()->all(),
                'cantidad_variantes' => $variants->count(),
                'whatsapp_url' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode('Hola, quiero consultar por la '.($first['model'] ?? 'moto').'.') : route('contacto'),
            ];
        })->values();
    }

    private function groupKey(array $row): string
    {
        foreach (['product_group', 'model_code', 'sku_base', 'commercial_model_id'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') return strtolower(trim((string) $row[$key]));
        }
        return $this->normalize((string) ($row['model'] ?? $row['name'] ?? ''));
    }

    /** @return array<string,mixed> */
    private function normalizePanelRow(array $row): array
    {
        return [
            'variant_id' => $row['variant_id'] ?? null,
            'model' => $row['model'] ?? '', 'name' => $row['model'] ?? '', 'slug' => $row['slug'] ?? '',
            'battery_ah' => $row['battery_ah'] ?? null, 'color' => $row['color'] ?? '', 'description' => $row['description'] ?? '',
            'price' => is_array($row['price'] ?? null)
                ? $row['price']
                : [
                    'gross' => $row['price'] ?? null,
                    'currency' => $row['currency'] ?? 'UYU',
                ],
            'currency' => $row['price']['currency'] ?? $row['currency'] ?? 'UYU',
            'availability' => $row['availability'] ?? [], 'gallery' => $row['gallery'] ?? [],
        ];
    }

    /** @return array<string,mixed> */
    private function normalizeLegacyRow(CatalogoMoto $row): array
    {
        return ['model' => $row->modelo ?: $row->nombre, 'name' => $row->nombre, 'slug' => $row->slug, 'battery_ah' => $row->capacidad_bateria_ah, 'color' => $row->color ?? '', 'description' => $row->descripcion, 'price' => $row->precio, 'currency' => $row->moneda, 'availability' => ['available' => (bool) $row->disponible, 'quantity' => (int) $row->stock]];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    private function buildEditorialFallback(): Collection
    {
        return collect(config('storefront.models', []))->map(function (array $definition, string $slug): object {
            $batteries = collect($definition['battery_options'] ?? [])->keys()->map(fn ($value) => (int) $value)->values()->all();
            $price = isset($definition['fallback_price']) ? (float) $definition['fallback_price'] : null;
            return (object) [
                'slug' => $slug, 'nombre' => $definition['name'], 'potencia_w' => preg_match('/(\d+)\s*W/i', $definition['name'], $match) ? (int) $match[1] : null,
                'descripcion' => $definition['description'], 'imagenes' => $definition['images'] ?? ['images/editorial/hero-principal.webp'], 'precio' => $price, 'moneda' => 'UYU', 'tiene_precio' => $price !== null,
                'colores' => [], 'bateria_ah' => $batteries === [] ? null : max($batteries), 'opciones_bateria' => $batteries, 'opciones_precio' => $definition['battery_options'] ?? [], 'disponible' => $price !== null, 'agotado' => false,
                'tiene_datos_reales' => false, 'variantes' => [], 'cantidad_variantes' => 0, 'whatsapp_url' => route('contacto'),
            ];
        })->values();
    }
}
