'use strict';
document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const sidebar = document.getElementById('sidebar');
        const isOpen = sidebar.classList.toggle('is-open');
        document.body.classList.toggle('sidebar-open', isOpen);
        document.querySelectorAll('[aria-controls="sidebar"]').forEach((button) => button.setAttribute('aria-expanded', String(isOpen)));
    });
});
(() => {
    const closers = [];
    document.querySelectorAll('[data-credential]').forEach((box) => {
        const field = box.closest('.form-field');
        const input = field?.querySelector('input[type="password"]');
        const button = box.querySelector('[data-credential-toggle]');
        if (!input || !button) return;
        const message = box.nextElementSibling;
        const name = input.name;
        let dirty = false, pending = null, timer = null;
        box.prepend(input);
        input.style.paddingRight = '48px';
        input.placeholder = '********';
        input.setAttribute('aria-description', 'Senha cadastrada. Use o olho para consultar ou digite para substituir.');
        const icons = (open) => {
            button.setAttribute('aria-pressed', String(open));
            button.setAttribute('aria-label', open ? 'Ocultar senha' : 'Mostrar senha');
            button.title = open ? 'Ocultar senha' : 'Mostrar senha';
            box.querySelector('[data-eye-open]').style.display = open ? 'block' : 'none';
            box.querySelector('[data-eye-closed]').style.display = open ? 'none' : 'block';
        };
        const close = () => {
            pending?.abort();
            pending = null;
            clearTimeout(timer);
            input.type = 'password';
            if (!dirty) { input.value = ''; input.name = name; }
            button.disabled = false;
            icons(false);
        };
        closers.push(close);
        input.addEventListener('beforeinput', () => {
            if (!dirty && !input.hasAttribute('name')) {
                input.value = '';
                input.name = name;
            }
        });
        input.addEventListener('input', () => {
            pending?.abort();
            pending = null;
            clearTimeout(timer);
            dirty = true;
            input.name = name;
            button.disabled = false;
        });
        field.closest('form')?.addEventListener('submit', () => {
            if (!dirty) close();
        });
        field.closest('form')?.addEventListener('reset', () => { dirty = false; close(); });
        button.addEventListener('click', async () => {
            if (input.type === 'text') { close(); return; }
            if (dirty) {
                input.type = 'text'; icons(true);
                timer = setTimeout(close, 30000);
                return;
            }
            if (pending) return;
            const token = box.closest('form')?.querySelector('input[name="_csrf"]')?.value;
            const url = new URL(box.dataset.credentialUrl, location.origin);
            if (!token || url.origin !== location.origin) return;
            const controller = new AbortController();
            pending = controller;
            button.disabled = true;
            if (message) message.textContent = '';
            try {
                const response = await fetch(url, {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                    headers: { Accept: 'application/json' },
                    body: new URLSearchParams({ _csrf: token }), signal: controller.signal,
                });
                if (!response.ok) throw new Error();
                const data = await response.json();
                if (controller.signal.aborted || document.hidden || dirty) return;
                if (typeof data.value !== 'string') throw new Error();
                input.removeAttribute('name');
                input.value = data.value;
                data.value = '';
                input.type = 'text';
                icons(true);
                timer = setTimeout(close, 30000);
            } catch {
                if (!controller.signal.aborted && message) message.textContent = 'Não foi possível consultar a credencial. Verifique sua sessão e tente novamente.';
            } finally {
                if (pending === controller) pending = null;
                button.disabled = false;
            }
        });
    });
    const closeAll = () => closers.forEach((close) => close());
    document.addEventListener('visibilitychange', () => { if (document.hidden) closeAll(); });
    window.addEventListener('pagehide', closeAll);
})();
