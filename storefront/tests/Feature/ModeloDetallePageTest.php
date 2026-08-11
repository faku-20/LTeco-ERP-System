<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ModeloDetallePageTest extends TestCase
{
    #[DataProvider('models')]
    public function test_model_detail_page_is_available(
        string $slug,
        string $name
    ): void {
        $response = $this->get(
            '/modelos/' . $slug
        );

        $response
            ->assertOk()
            ->assertSee(
                '<title>'
                . $name
                . ' | ERP</title>',
                false,
            )
            ->assertSee($name)
            ->assertSee(
                'data-model-carousel',
                false,
            )
            ->assertSee('Ficha completa')
            ->assertSee('Potencia')
            ->assertSee('Consultar por WhatsApp')
            ->assertSee('Ver todos los modelos')
            ->assertSee('/modelos', false)
            ->assertDontSee('#modelos', false)
            ->assertDontSee('#ayuda', false)
            ->assertSee('Autonomía aproximada')
            ->assertSee('Velocidad máxima')
            ->assertSee('Garantía');
    }

    public function test_unknown_model_returns_not_found(): void
    {
        $this
            ->get('/modelos/modelo-inexistente')
            ->assertNotFound();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function models(): array
    {
        return [
            'Q8 500' => [
                'q8-500',
                'Q8-500W',
            ],
            'Q8 350' => [
                'q8-350',
                'Q8-350W',
            ],
            'SL 500' => [
                'sl-500',
                'SL-500W',
            ],
        ];
    }
}
