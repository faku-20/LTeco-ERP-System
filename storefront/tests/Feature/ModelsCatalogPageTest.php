<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ModelsCatalogPageTest extends TestCase
{
    public function test_models_page_uses_catalog_models(): void
    {
        $response = $this->get('/modelos');

        $response
            ->assertOk()
            ->assertSee('Elegí tu modelo ideal')
            ->assertSee('Q8-500W')
            ->assertSee('Q8-350W')
            ->assertSee('SL-500W')
            ->assertDontSee('LY-500W')
            ->assertSee('Potencia')
            ->assertSee('Ver ficha completa')
            ->assertSee('/modelos/q8-500', false)
            ->assertSee('/modelos/q8-350', false)
            ->assertSee('/modelos/sl-500', false)
            ->assertDontSee('/modelos/ly-500', false);
    }

    public function test_models_page_has_no_unverified_specs(): void
    {
        $this->get('/modelos')
            ->assertOk()
            ->assertDontSee('Autonomía aproximada')
            ->assertDontSee('Velocidad máxima aproximada')
            ->assertDontSee('Garantía y service oficial');
    }

    public function test_unknown_published_panel_model_appears_without_static_configuration(): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.test/api/storefront/v1');
        config()->set('storefront_api.panel.secret', 'secret');
        $variantId = str_repeat('a', 64);
        Http::fake(['*' => Http::response(['data' => [[
            'variant_id' => $variantId,
            'model' => 'NUEVO MODELO 700W',
            'slug' => '',
            'battery_ah' => 20,
            'color' => 'Azul',
            'description' => '',
            'gallery' => [],
            'availability' => ['available' => true, 'quantity' => 1],
            'price' => ['currency' => 'UYU', 'gross' => '70000.00'],
        ]]], 200)]);

        $this->get('/modelos')
            ->assertOk()
            ->assertSee('NUEVO MODELO 700W')
            ->assertSee('/modelos/nuevo-modelo-700w', false)
            ->assertSee('hero-principal.webp', false)
            ->assertSee('js/storefront.js', false);

        $this->get('/modelos/nuevo-modelo-700w')
            ->assertOk()
            ->assertSee('NUEVO MODELO 700W');
    }

    public function test_model_card_tags_variant_images_by_color(): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.test/api/storefront/v1');
        config()->set('storefront_api.panel.secret', 'secret');
        Http::fake(['*' => Http::response(['data' => [
            [
                'variant_id' => str_repeat('c', 64),
                'model' => 'SL-500W',
                'slug' => 'sl-500',
                'battery_ah' => 20,
                'color' => 'Azul',
                'description' => 'SL publicada.',
                'gallery' => [['url' => 'https://media.test/sl-azul.webp']],
                'availability' => ['available' => true, 'quantity' => 1],
                'price' => ['currency' => 'UYU', 'gross' => '65000.00'],
            ],
            [
                'variant_id' => str_repeat('d', 64),
                'model' => 'SL-500W',
                'slug' => 'sl-500',
                'battery_ah' => 20,
                'color' => 'Beige',
                'description' => 'SL publicada.',
                'gallery' => [['url' => 'https://media.test/sl-beige.webp']],
                'availability' => ['available' => true, 'quantity' => 1],
                'price' => ['currency' => 'UYU', 'gross' => '65000.00'],
            ],
        ]], 200)]);

        $this->get('/modelos')
            ->assertOk()
            ->assertSee('data-carousel-color="Azul"', false)
            ->assertSee('https://media.test/sl-azul.webp', false)
            ->assertSee('data-carousel-color="Beige"', false)
            ->assertSee('https://media.test/sl-beige.webp', false)
            ->assertSee('data-variant-color', false);
    }

    public function test_successful_empty_panel_catalog_does_not_restore_static_models(): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.test/api/storefront/v1');
        config()->set('storefront_api.panel.secret', 'secret');
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->get('/modelos')
            ->assertOk()
            ->assertSee('Catálogo temporalmente no disponible')
            ->assertDontSee('Q8-500W');
    }

    public function test_published_zero_stock_model_is_visible_as_sold_out(): void
    {
        config()->set('storefront_api.panel.base_url', 'https://panel.test/api/storefront/v1');
        config()->set('storefront_api.panel.secret', 'secret');
        Http::fake(['*' => Http::response(['data' => [[
            'variant_id' => str_repeat('b', 64),
            'model' => 'MODELO AGOTADO 500W',
            'slug' => 'modelo-agotado',
            'battery_ah' => 12,
            'color' => 'Negro',
            'description' => 'Modelo publicado sin stock.',
            'gallery' => [],
            'availability' => ['available' => false, 'quantity' => 0],
            'price' => ['currency' => 'UYU', 'gross' => '60000.00'],
        ]]], 200)]);

        $this->get('/modelos')
            ->assertOk()
            ->assertSee('MODELO AGOTADO 500W')
            ->assertSee('Agotado')
            ->assertSee('disabled', false);
    }
}
