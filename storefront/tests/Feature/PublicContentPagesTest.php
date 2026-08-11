<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PublicContentPagesTest extends TestCase
{
    public function test_active_public_pages_are_available(): void
    {
        $pages = [
            '/modelos' => 'Elegí tu modelo ideal',
            '/nosotros' => 'Sobre CommerceOps',
            '/contacto' => 'Hablemos',
            '/privacidad' => 'Política de privacidad',
            '/agenda' => 'Agendá tu visita',
        ];

        foreach ($pages as $path => $expectedText) {
            $this
                ->get($path)
                ->assertOk()
                ->assertSee($expectedText);
        }
    }

    public function test_deferred_pages_are_not_active(): void
    {
        $pages = [
            '/como-comprar',
            '/service',
            '/preguntas-frecuentes',
            '/terminos-de-compra',
            '/cambios-devoluciones-y-garantia',
            '/ayuda',
        ];

        foreach ($pages as $path) {
            $this
                ->get($path)
                ->assertNotFound();
        }
    }

    public function test_models_index_links_all_model_pages(): void
    {
        $this
            ->get('/modelos')
            ->assertOk()
            ->assertSee('/modelos/q8-500', false)
            ->assertSee('/modelos/q8-350', false)
            ->assertSee('/modelos/sl-500', false)
            ->assertDontSee('/modelos/ly-500', false);
    }

    public function test_navigation_contains_only_current_pages(): void
    {
        $response = $this->get('/nosotros');

        $response
            ->assertOk()
            ->assertSee('/modelos', false)
            ->assertSee('/nosotros', false)
            ->assertSee('/contacto', false)
            ->assertSee('/privacidad', false)
            ->assertSee('/agenda', false)
            ->assertDontSee('/como-comprar', false)
            ->assertDontSee('/service', false)
            ->assertDontSee('/terminos-de-compra', false);
    }

    public function test_robots_and_sitemap_are_available(): void
    {
        config()->set('storefront_seo.indexable',true);
        $robots=$this->get('/robots.txt')->assertOk()->assertHeader('Content-Type','text/plain; charset=UTF-8')->getContent();
        $sitemap=$this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type','application/xml; charset=UTF-8')->getContent();

        self::assertStringContainsString('sitemap.xml',$robots);

        self::assertStringContainsString(
            '/modelos/q8-500',
            $sitemap
        );

        self::assertStringContainsString(
            '/privacidad',
            $sitemap
        );

        self::assertStringContainsString(
            '/agenda',
            $sitemap
        );

        self::assertStringContainsString(
            '/calculadora-ahorro',
            $sitemap
        );

        self::assertStringNotContainsString(
            '/service',
            $sitemap
        );

        self::assertStringNotContainsString(
            '/terminos-de-compra',
            $sitemap
        );
    }
}
