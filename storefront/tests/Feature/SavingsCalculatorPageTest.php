<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class SavingsCalculatorPageTest extends TestCase
{
    public function test_calculator_contains_gas_bus_and_electric_modes(): void
    {
        $this->get('/calculadora-ahorro')
            ->assertOk()
            ->assertSee('Vehículo a nafta')
            ->assertSee('Ómnibus urbano')
            ->assertSee('Precio del boleto')
            ->assertSee('Boletos por día')
            ->assertSee('Días de viaje al mes')
            ->assertSee('Diferencia mensual estimada')
            ->assertSee('Diferencia anual estimada')
            ->assertSee('savings-calculator.js', false);
    }
}
