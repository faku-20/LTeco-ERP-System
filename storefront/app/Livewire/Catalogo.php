<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\StorefrontCatalogService;
use Illuminate\Support\Str;
use Livewire\Component;

final class Catalogo extends Component
{
    public string $buscar = '';

    public bool $datosEnTiempoRealDisponibles = true;

    public function render()
    {
        $catalog = app(
            StorefrontCatalogService::class
        )->load();

        $this->datosEnTiempoRealDisponibles = (
            $catalog['realtime']
        );

        $motos = $catalog['models'];

        $buscar = $this->normalize($this->buscar);

        if ($buscar !== '') {
            $motos = $motos
                ->filter(
                    function (
                        object $moto
                    ) use (
                        $buscar
                    ): bool {
                        $content = $this->normalize(
                            implode(
                                ' ',
                                [
                                    $moto->nombre,
                                    $moto->descripcion,
                                    implode(
                                        ' ',
                                        $moto->colores,
                                    ),
                                ],
                            ),
                        );

                        return str_contains(
                            $content,
                            $buscar,
                        );
                    },
                )
                ->values();
        }

        return view(
            'livewire.catalogo',
            [
                'motos' => $motos,
            ],
        );
    }

    private function normalize(
        string $value
    ): string {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches(
                '/[^a-z0-9]+/',
                ' ',
            )
            ->squish()
            ->toString();
    }
}
