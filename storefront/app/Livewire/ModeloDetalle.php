<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\StorefrontCatalogService;
use Livewire\Component;

final class ModeloDetalle extends Component
{
    public string $slug;

    public function mount(
        string $slug
    ): void {
        $this->slug = $slug;
    }

    public function render()
    {
        $moto = app(
            StorefrontCatalogService::class
        )->find($this->slug);

        abort_if(
            $moto === null,
            404,
        );

        return view(
            'livewire.modelo-detalle',
            [
                'moto' => $moto,
            ],
        );
    }
}
