<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class InstitutionalPagesTest extends TestCase
{
    public function test_about_page_has_current_scope(): void
    {
        $this
            ->get('/nosotros')
            ->assertOk()
            ->assertSee('Sobre CommerceOps')
            ->assertSee('Nuestra misión')
            ->assertSee('Modelos para la ciudad')
            ->assertSee('Atención cercana')
            ->assertSee('Repuestos y respaldo')
            ->assertSee('/modelos', false)
            ->assertSee('/calculadora-ahorro', false)
            ->assertSee('Calculadora de ahorro')
            ->assertSee('storefront-floating-action--top', false)
            ->assertSee('garantía')
            ->assertSee('postventa')
            ->assertSee('repuestos')
            ->assertSee('Consultá disponibilidad ahora');
    }

    public function test_contact_page_has_real_channels(): void
    {
        $response = $this->get('/contacto');

        $response
            ->assertOk()
            ->assertSee('Hablemos')
            ->assertSee('WhatsApp')
            ->assertSee('@tuempresa')
            ->assertSee('Tu zona de atención')
            ->assertSee('/agenda', false)
            ->assertSee('Ver catálogo')
            ->assertSee('/modelos', false)
            ->assertSee('postventa');
    }

    public function test_contact_links_are_external_and_safe(): void
    {
        $this
            ->get('/contacto')
            ->assertOk()
            ->assertSee(
                'rel="noopener noreferrer"',
                false,
            )
            ->assertDontSee(
                'https://wa.me/59892000086',
                false,
            );
    }
}
