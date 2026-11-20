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

(() => {
    const pending = new WeakMap();
    const timers = new WeakMap();
    const close = (container) => {
        pending.get(container)?.abort();
        pending.delete(container);
        clearTimeout(timers.get(container));
        timers.delete(container);
        const output = container.querySelector('[data-credential-value]');
        const button = container.querySelector('[data-credential-toggle]');
        if (output) {
            output.textContent = '';
            output.hidden = true;
        }
        if (button) {
            button.setAttribute('aria-pressed', 'false');
            button.setAttribute('aria-label', 'Mostrar senha');
            button.title = 'Mostrar senha';
            button.querySelector('[data-eye-open]')?.setAttribute('hidden', '');
            button.querySelector('[data-eye-closed]')?.removeAttribute('hidden');
        }
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-credential-toggle]');
        if (!button) return;
        const container = button.closest('[data-credential]');
        const output = container?.querySelector('[data-credential-value]');
        const message = container?.querySelector('[data-credential-message]');
        if (!container || !output) return;
        if (button.getAttribute('aria-pressed') === 'true') {
            close(container);
            return;
        }
        if (pending.has(container)) return;
        const token = container.querySelector('input[name="_csrf"]')?.value
            || container.closest('form')?.querySelector('input[name="_csrf"]')?.value;
        const url = new URL(container.dataset.credentialUrl, window.location.origin);
        if (!token || url.origin !== window.location.origin) {
            if (message) message.textContent = 'Não foi possível consultar a credencial.';
            return;
        }
        const controller = new AbortController();
        pending.set(container, controller);
        button.disabled = true;
        if (message) message.textContent = '';
        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'error',
                headers: { 'Accept': 'application/json' },
                body: new URLSearchParams({ _csrf: token }),
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Consulta recusada');
            const data = await response.json();
            if (controller.signal.aborted || document.hidden) return;
            if (typeof data.value !== 'string') throw new Error('Resposta inválida');
            output.textContent = data.value;
            data.value = '';
            output.hidden = false;
            button.setAttribute('aria-pressed', 'true');
            button.setAttribute('aria-label', 'Ocultar senha');
            button.title = 'Ocultar senha';
            button.querySelector('[data-eye-open]')?.removeAttribute('hidden');
            button.querySelector('[data-eye-closed]')?.setAttribute('hidden', '');
            timers.set(container, setTimeout(() => close(container), 30000));
        } catch {
            if (!controller.signal.aborted && message) {
                message.textContent = 'Não foi possível consultar a credencial. Verifique sua sessão e tente novamente.';
            }
        } finally {
            if (pending.get(container) === controller) pending.delete(container);
            button.disabled = false;
        }
    });

    const closeAll = () => document.querySelectorAll('[data-credential]').forEach(close);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) closeAll();
    });
    window.addEventListener('pagehide', closeAll);
})();
