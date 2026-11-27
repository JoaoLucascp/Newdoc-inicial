'use strict';
document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.dialogOpen);
        if (dialog && typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
    });
});
document.querySelectorAll('[data-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});
document.querySelectorAll('dialog.nbi-dialog[data-open]').forEach((dialog) => {
    if (typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
});
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});