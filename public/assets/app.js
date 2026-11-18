'use strict';

document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const sidebar = document.getElementById('sidebar');
        const isOpen = sidebar.classList.toggle('is-open');
        document.body.classList.toggle('sidebar-open', isOpen);
        document.querySelectorAll('[aria-controls="sidebar"]').forEach((button) => {
            button.setAttribute('aria-expanded', String(isOpen));
        });
    });
});
