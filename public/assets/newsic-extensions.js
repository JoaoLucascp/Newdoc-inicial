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

(() => {
    const entries = [];
    let generation = 0;
    document.querySelectorAll('.extensions-table .extension-summary .extension-secret [data-credential]').forEach((box) => {
        const form = box.closest('form');
        const input = box.querySelector('input[readonly]');
        const token = form?.querySelector('input[name="_csrf"]')?.value;
        if (!input || !token) return;
        const url = new URL(box.dataset.credentialUrl, location.origin);
        if (url.origin !== location.origin || !/^\/credenciais\/newsic_extension\/[1-9][0-9]*\/revelar$/.test(url.pathname)) return;
        box.querySelector('[data-credential-toggle]')?.remove();
        input.removeAttribute('name');
        input.style.paddingRight = '10px';
        input.setAttribute('aria-description', 'Senha de registro somente para consulta. Para alterar, use Editar.');
        entries.push({ input, token, url, message: box.nextElementSibling, controller: null });
    });
    const clear = () => {
        generation++;
        entries.forEach((entry) => {
            entry.controller?.abort();
            entry.controller = null;
            entry.input.value = '';
            entry.input.type = 'password';
            entry.input.placeholder = '********';
            entry.input.removeAttribute('name');
            if (entry.message) entry.message.textContent = '';
        });
    };
    const loadEntry = async (entry, version) => {
        if (version !== generation || document.hidden) return;
        const controller = new AbortController();
        entry.controller = controller;
        entry.input.placeholder = 'Carregando...';
        try {
            const response = await fetch(entry.url, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                headers: { Accept: 'application/json' },
                body: new URLSearchParams({ _csrf: entry.token }), signal: controller.signal,
            });
            if (!response.ok) throw new Error();
            const data = await response.json();
            if (controller.signal.aborted || version !== generation || document.hidden) return;
            if (typeof data.value !== 'string') throw new Error();
            entry.input.removeAttribute('name');
            entry.input.type = 'text';
            entry.input.value = data.value;
            data.value = '';
            entry.input.placeholder = '';
        } catch {
            if (!controller.signal.aborted && version === generation) {
                entry.input.value = '';
                entry.input.placeholder = 'Indisponível';
                if (entry.message) entry.message.textContent = 'Não foi possível consultar a senha. Verifique sua sessão e atualize a lista.';
            }
        } finally {
            if (entry.controller === controller) entry.controller = null;
        }
    };
    const load = async () => {
        clear();
        if (document.hidden) return;
        const version = generation;
        for (let offset = 0; offset < entries.length; offset += 4) {
            if (version !== generation || document.hidden) return;
            await Promise.all(entries.slice(offset, offset + 4).map((entry) => loadEntry(entry, version)));
        }
    };
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) clear();
        else void load();
    });
    window.addEventListener('pagehide', clear);
    window.addEventListener('pageshow', (event) => { if (event.persisted) void load(); });
    void load();
})();
