// Admin: confirm destructive actions (inline handlers are blocked by the CSP).
for (const form of document.querySelectorAll('form[data-confirm]')) {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
}
