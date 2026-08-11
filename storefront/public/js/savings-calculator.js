document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-savings-calculator]');
    if (!root) return;

    const field = (name) => root.querySelector(`[data-${name}]`);
    const number = (name, fallback = 0) => {
        const value = Number(field(name)?.value);
        return Number.isFinite(value) ? value : fallback;
    };
    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
    const money = (value) => new Intl.NumberFormat('es-UY', {
        style: 'currency',
        currency: 'UYU',
        maximumFractionDigits: 0,
    }).format(Math.round(value));
    const fallbackGasEfficiencyKmL = 30;

    const calculate = () => {
        const mode = root.querySelector('[data-savings-mode]:checked')?.value || 'gas';
        const dailyKm = clamp(number('daily-km'), 0, 1000);
        const gasPrice = Math.max(number('gas-price'), 0);
        const gasEfficiency = fallbackGasEfficiencyKmL;
        const electricPrice = Math.max(
            mode === 'bus'
                ? number('electric-price-default', 8)
                : number('electric-price', 8),
            0,
        );
        const electricEfficiency = Math.max(number('electric-efficiency', 45), 0.01);
        const travelDays = mode === 'bus' ? clamp(number('travel-days'), 1, 31) : 30;
        const monthlyKm = dailyKm * travelDays;
        const electricMonthly = (monthlyKm / electricEfficiency) * electricPrice;

        let comparisonMonthly;
        let comparisonLabel;

        if (mode === 'bus') {
            const ticketPrice = Math.max(number('ticket-price'), 0);
            const ticketsPerDay = clamp(number('tickets-per-day'), 0, 20);
            comparisonMonthly = ticketPrice * ticketsPerDay * travelDays;
            comparisonLabel = 'Ómnibus';
        } else {
            comparisonMonthly = (monthlyKm / gasEfficiency) * gasPrice;
            comparisonLabel = 'Nafta';
        }

        const differenceMonthly = comparisonMonthly - electricMonthly;

        field('daily-output').textContent = `${Math.round(dailyKm)} km`;
        field('comparison-monthly-label').textContent = `${comparisonLabel} mensual estimado`;
        field('comparison-yearly-label').textContent = `${comparisonLabel} anual estimado`;
        field('comparison-monthly').textContent = money(comparisonMonthly);
        field('comparison-yearly').textContent = money(comparisonMonthly * 12);
        field('electric-monthly').textContent = money(electricMonthly);
        field('difference-monthly').textContent = money(differenceMonthly);
        field('difference-yearly').textContent = money(differenceMonthly * 12);

        root.querySelectorAll('[data-gas-field]').forEach((element) => {
            element.hidden = mode !== 'gas';
            element.querySelectorAll('input').forEach((input) => { input.disabled = mode !== 'gas'; });
        });
        root.querySelectorAll('[data-bus-field]').forEach((element) => {
            element.hidden = mode !== 'bus';
            element.querySelectorAll('input').forEach((input) => { input.disabled = mode !== 'bus'; });
        });
    };

    root.querySelectorAll('input').forEach((input) => {
        input.addEventListener('input', calculate);
        input.addEventListener('change', calculate);
    });
    calculate();
});
