<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

final class PublicMediaUrl
{
    public function __construct(private string $origin) {}

    public function fromStoredPath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, '..') || preg_match('#^https?://#i', $path) === 1) {
            return null;
        }
        $path = '/' . ltrim($path, '/');
        if (!str_starts_with($path, '/uploads/vehiculos/')) {
            return null;
        }
        if (preg_match('#\.(?:jpe?g|png|webp|gif)$#i', $path) !== 1) {
            return null;
        }
        return rtrim($this->origin, '/') . $path;
    }
}
