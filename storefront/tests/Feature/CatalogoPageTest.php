<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class CatalogoPageTest extends TestCase
{
    public function test_home_is_a_multipage_portal(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Bici-motos y scooters eléctricos')
            ->assertSee('Últimos ingresos')
            ->assertSee('Q8 500W')
            ->assertSee('SL 500W')
            ->assertDontSee('LY-500W')
            ->assertSee('/modelos', false)
            ->assertSee('/nosotros', false)
            ->assertSee('/contacto', false)
            ->assertDontSee('href="#modelos"', false)
            ->assertDontSee('href="#como-comprar"', false)
            ->assertSee('data-home-gallery', false);
    }

    public function test_home_links_individual_model_pages(): void
    {
        $this
            ->get('/')
            ->assertOk()
            ->assertSee('/modelos/q8-500-w-v0230', false)
            ->assertSee('/modelos/sl-500-20ah-beige-v0051', false)
            ->assertDontSee('/modelos/ly-500', false);
    }
}
