'use strict';
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-extension-toggle]');
    if (!button) return;
    const target = document.getElementById(button.getAttribute('aria-controls'));
    if (!target) return;
    target.hidden = !target.hidden;
    document.querySelectorAll('[data-extension-toggle]').forEach((control) => {
        if (control.getAttribute('aria-controls') === target.id) control.setAttribute('aria-expanded', String(!target.hidden));
    });
    if (!target.hidden) {
        target.scrollIntoView({ block: 'nearest' });
        target.querySelector('input:not([type="hidden"]):not([readonly])')?.focus({ preventScroll: true });
    }
});
