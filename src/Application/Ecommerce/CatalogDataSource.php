<?php

declare(strict_types=1);

namespace Lteco\Application\Ecommerce;

interface CatalogDataSource
{
    /** @return array{units:list<array<string,mixed>>,images:list<array<string,mixed>>} */
    public function catalogSnapshot(): array;
}
