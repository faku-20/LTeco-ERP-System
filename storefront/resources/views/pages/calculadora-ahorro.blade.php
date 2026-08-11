@extends('layouts.storefront-public')
@section('title','Calculadora de ahorro: nafta, ómnibus y eléctrico | CommerceOps')
@section('description','Compará el costo estimado de viajar en nafta u ómnibus urbano frente a la carga eléctrica.')
@section('content')
<section class="savings-calculator" data-savings-calculator>
    <div class="official-container savings-calculator__grid">
        <header>
            <p class="official-kicker">Movilidad comparada</p>
            <h1>Calculadora de ahorro</h1>
            <p>Elegí contra qué querés comparar y completá tus valores. La estimación usa el mismo período de viaje para ambas alternativas.</p>
        </header>
        <form class="savings-calculator__form" onsubmit="return false">
            <fieldset class="savings-mode">
                <legend>Comparar la movilidad eléctrica contra</legend>
                <label><input type="radio" name="savings_mode" value="gas" data-savings-mode checked> Vehículo a nafta</label>
                <label><input type="radio" name="savings_mode" value="bus" data-savings-mode> Ómnibus urbano</label>
            </fieldset>

            <label>¿Cuántos kilómetros recorrés por día?
                <strong><output data-daily-output>25 km</output></strong>
                <input type="range" min="5" max="120" step="5" value="25" data-daily-km>
            </label>

            <div class="savings-calculator__inputs">
                <label data-gas-field>Precio de nafta (UYU/litro)<input type="number" min="0.01" step="0.01" value="76" data-gas-price inputmode="decimal"></label>

                <label data-bus-field hidden>Precio del boleto (UYU)
                    <input type="number" min="0" step="0.01" value="{{ config('storefront_content.savings.default_ticket_price') > 0 ? config('storefront_content.savings.default_ticket_price') : '' }}" placeholder="Ingresá el valor" data-ticket-price inputmode="decimal">
                </label>
                <label data-bus-field hidden>Boletos por día<input type="number" min="0" max="20" step="1" value="2" data-tickets-per-day inputmode="numeric"></label>
                <label data-bus-field hidden>Días de viaje al mes<input type="number" min="1" max="31" step="1" value="22" data-travel-days inputmode="numeric"></label>

                <label data-gas-field>Tarifa eléctrica (UYU/kWh)<input type="number" min="0.01" step="0.01" value="8" data-electric-price inputmode="decimal"></label>
                <input type="hidden" value="8" data-electric-price-default>
                <div class="savings-assumption">
                    <span>Rendimiento eléctrico CommerceOps</span>
                    <strong>45 km/kWh</strong>
                    <input type="hidden" value="45" data-electric-efficiency>
                </div>
                <p class="savings-input-note" data-bus-field hidden>El valor del boleto es editable; no se presenta como tarifa oficial vigente.</p>
            </div>

            <div class="savings-results" aria-live="polite">
                <div><span data-comparison-monthly-label>Nafta mensual estimada</span><strong data-comparison-monthly>$ 0</strong></div>
                <div><span data-comparison-yearly-label>Nafta anual estimada</span><strong data-comparison-yearly>$ 0</strong></div>
                <div><span>Carga eléctrica mensual estimada</span><strong data-electric-monthly>$ 0</strong></div>
                <div><span>Diferencia mensual estimada</span><strong data-difference-monthly>$ 0</strong></div>
                <div class="savings-results__featured"><span>Diferencia anual estimada</span><strong data-difference-yearly>$ 0</strong></div>
            </div>

            <p class="savings-calculator__note">Cálculo estimativo. No incluye el precio de compra del vehículo, financiación, batería, seguro, patente, mantenimiento ni variaciones futuras en las tarifas.</p>
        </form>
    </div>
</section>
@endsection
@push('scripts')
    <script src="{{ asset('js/savings-calculator.js') }}?v={{ filemtime(public_path('js/savings-calculator.js')) }}&hotfix=20260728_fixed_efficiency" defer></script>
@endpush
