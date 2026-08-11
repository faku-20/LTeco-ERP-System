document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('[data-account-customer-type]');
    if (!select) return;

    const update = () => {
        document.querySelectorAll('[data-account-customer-fields]').forEach((group) => {
            const active = group.dataset.accountCustomerFields === select.value;
            group.hidden = !active;
            group.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !active;
            });
        });
    };

    select.addEventListener('change', update);
    update();
});
