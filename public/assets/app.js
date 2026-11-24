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

(() => {
    const match = location.pathname.match(/^\/newsic\/([1-9][0-9]*)$/);
    if (!match || typeof HTMLDialogElement === 'undefined' || typeof HTMLDialogElement.prototype.showModal !== 'function') return;
    const path = '/newsic/' + match[1] + '/exportar';
    const trigger = Array.from(document.querySelectorAll('a[href]')).find((a) => a.getAttribute('href') === path);
    if (!trigger) return;
    const stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    stylesheet.href = '/assets/newsic-export-modal.css';
    document.head.append(stylesheet);
    const dialog = document.createElement('dialog');
    dialog.className = 'newsic-export-dialog';
    dialog.setAttribute('aria-labelledby', 'newsic-export-title');
    const header = document.createElement('div');
    header.className = 'newsic-export-dialog-heading';
    const title = document.createElement('h2');
    title.id = 'newsic-export-title';
    title.textContent = 'Exportar documentação Newsic';
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'button button-secondary';
    close.textContent = 'Fechar';
    close.autofocus = true;
    header.append(title, close);
    const status = document.createElement('p');
    status.className = 'newsic-export-dialog-status';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    const content = document.createElement('div');
    content.className = 'newsic-export-dialog-content';
    dialog.append(header, status, content);
    document.body.append(dialog);
    let loading = null;
    let busy = false;
    close.addEventListener('click', () => { if (!busy) dialog.close(); });
    dialog.addEventListener('cancel', (event) => { if (busy) event.preventDefault(); });
    dialog.addEventListener('close', () => {
        loading?.abort();
        loading = null;
        content.replaceChildren();
        status.textContent = '';
        document.body.classList.remove('newsic-export-modal-open');
        trigger.focus();
    });
    trigger.addEventListener('click', async (event) => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (dialog.open) return;
        document.body.classList.add('newsic-export-modal-open');
        status.textContent = 'Carregando opções de exportação…';
        dialog.showModal();
        const controller = new AbortController();
        loading = controller;
        try {
            const response = await fetch(path, { credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal: controller.signal });
            if (!response.ok) throw new Error();
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (controller.signal.aborted || !dialog.open) return;
            const panels = page.querySelectorAll('.page-content > .panel');
            if (panels.length !== 2) throw new Error();
            const subtitle = page.querySelector('.page-subtitle');
            if (subtitle) {
                const label = document.createElement('p');
                label.className = 'page-subtitle';
                label.textContent = subtitle.textContent;
                content.append(label);
            }
            panels.forEach((panel) => {
                const copy = document.importNode(panel, true);
                copy.querySelectorAll('script, iframe, object, embed').forEach((node) => node.remove());
                copy.querySelectorAll('.form-actions a').forEach((node) => node.remove());
                content.append(copy);
            });
            status.textContent = '';
        } catch {
            if (!controller.signal.aborted) status.textContent = 'Não foi possível carregar as opções. Feche e tente novamente. Se a sessão expirou, atualize a página.';
        } finally {
            if (loading === controller) loading = null;
        }
    });
    dialog.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        const action = new URL(form.action, location.origin);
        if (action.origin !== location.origin || action.pathname !== path || form.method.toLowerCase() !== 'post') return;
        const data = new FormData(form);
        const format = data.get('format');
        if (!['pdf', 'csv'].includes(format)) return;
        if (format === 'csv' && data.get('confirm_secrets') !== '1') return;
        busy = true;
        close.disabled = true;
        const buttons = Array.from(content.querySelectorAll('button[type="submit"]'));
        buttons.forEach((button) => { button.disabled = true; });
        content.setAttribute('aria-busy', 'true');
        status.textContent = 'Gerando arquivo. Aguarde sem fechar esta janela…';
        try {
            const response = await fetch(action, { method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store', redirect: 'error' });
            const type = (response.headers.get('Content-Type') || '').split(';')[0].trim().toLowerCase();
            if (!response.ok || type !== (format === 'pdf' ? 'application/pdf' : 'text/csv')) throw new Error();
            const blob = await response.blob();
            if (blob.size === 0) throw new Error();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            const name = (response.headers.get('Content-Disposition') || '').match(/filename="(newsic-[0-9]+-[0-9-]+\.(?:pdf|csv))"/);
            link.download = name ? name[1] : 'newsic-' + match[1] + '.' + format;
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
            form.reset();
            status.textContent = 'Arquivo recebido. Download solicitado ao navegador.';
        } catch {
            status.textContent = 'Não foi possível concluir o download. Confira os downloads do navegador antes de tentar novamente. Se a sessão expirou, atualize a página.';
        } finally {
            busy = false;
            close.disabled = false;
            buttons.forEach((button) => { button.disabled = false; });
            content.removeAttribute('aria-busy');
        }
    });
})();
