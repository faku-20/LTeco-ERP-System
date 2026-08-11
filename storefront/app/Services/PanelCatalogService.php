<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use RuntimeException;

final class PanelCatalogService
{
    public function __construct(private readonly PanelApiClient $panel) {}

    /** @return Collection<int,array<string,mixed>> */
    public function allVariants(): Collection
    {
        $response = $this->panel->catalog();
        if (!$response->successful()) {
            throw new RuntimeException('El catálogo no está disponible temporalmente.');
        }
        $data = $response->json('data');
        if (!is_array($data)) throw new RuntimeException('El panel devolvió un catálogo inválido.');
        return collect($data)->filter(fn(mixed $row): bool => is_array($row) && preg_match('/^[a-f0-9]{64}$/', (string) ($row['variant_id'] ?? '')) === 1)->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    public function variants(): Collection
    {
        return $this->allVariants()->filter(fn (array $row): bool => ($row['availability']['available'] ?? false) === true && (int) ($row['availability']['quantity'] ?? 0) > 0)->values();
    }

    /** @return array<string,mixed>|null */
    public function findVariant(string $variantId): ?array
    {
        $variant = $this->variants()->firstWhere('variant_id', $variantId);
        return is_array($variant) ? $variant : null;
    }
}
