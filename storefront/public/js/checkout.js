document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('[data-customer-type]');
    if (!select) return;
    const update = () => document.querySelectorAll('[data-customer-fields]').forEach((group) => {
        const active = group.dataset.customerFields === select.value;
        group.hidden = !active;
        group.querySelectorAll('input').forEach((input) => { input.disabled = !active; });
    });
    select.addEventListener('change', update);
    update();
});
