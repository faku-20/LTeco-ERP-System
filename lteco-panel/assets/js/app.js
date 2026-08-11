document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-flash-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const toast = button.closest('[data-flash-toast]');
            if (!toast) {
                return;
            }

            toast.classList.add('is-hiding');
            window.setTimeout(() => {
                toast.hidden = true;
            }, 180);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('[data-flash-toast]:not([hidden])').forEach((toast) => {
            toast.classList.add('is-hiding');
            window.setTimeout(() => {
                toast.hidden = true;
            }, 180);
        });
    });
});

/* V3.2.5 - Atajo global de búsqueda */
document.addEventListener("keydown", (event) => {
    const isSearchShortcut =
    event.altKey &&
    event.key.toLowerCase() === "k";

    if (!isSearchShortcut) return;

    const target = event.target;
    const tag = target?.tagName?.toLowerCase();

    if (
        tag === "input" ||
        tag === "textarea" ||
        target?.isContentEditable
    ) {
        return;
    }

    event.preventDefault();
    window.location.href = (window.LTECO_PANEL_BASE || "") + "/busqueda/index.php";
});
