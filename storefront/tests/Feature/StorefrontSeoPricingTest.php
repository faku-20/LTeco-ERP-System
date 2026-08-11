<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class StorefrontSeoPricingTest extends TestCase
{
    public function test_staging_is_not_indexable_by_default(): void
    {
        config()->set('storefront_seo.indexable',false);

        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Robots-Tag','noindex, nofollow, noarchive')
            ->assertSee('noindex,nofollow,noarchive',false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /');
    }

    public function test_home_displays_the_confirmed_model_starting_prices(): void
    {
        $this->get('/modelos')
            ->assertOk()
            ->assertSee('$ 57.600')
            ->assertSee('$ 63.000')
            ->assertSee('$ 65.000')
            ->assertSee('data-variant-price', false);
    }

    public function test_savings_calculator_and_structured_product_data_are_available(): void
    {
        $this->get('/calculadora-ahorro')
            ->assertOk()
            ->assertSee('Calculadora de ahorro')
            ->assertSee('data-savings-calculator',false)
            ->assertSee('/js/savings-calculator.js',false);

        $this->get('/modelos/q8-500')
            ->assertOk()
            ->assertSee('"@type":"Product"',false)
            ->assertSee('"price":"63000"',false);
    }

    public function test_online_payment_remains_closed_until_provider_is_approved(): void
    {
        self::assertSame('none',config('storefront_payments.provider'));
        self::assertFalse(config('storefront_payments.online_enabled'));
        self::assertSame('sandbox',config('storefront_payments.getnet.environment'));
    }
}
