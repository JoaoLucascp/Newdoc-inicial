'use strict';
(() => {
    let index = 0;
    document.querySelectorAll('.extensions-panel').forEach((panel) => {
        const form = panel.querySelector('[data-extension-delete-form]');
        if (!form) return;
        const boxes = Array.from(panel.querySelectorAll('[data-extension-select]'));
        const all = panel.querySelector('[data-extension-select-all]');
        const button = form.querySelector('button[type="submit"]');
        const status = form.querySelector('[data-extension-selection-status]');
        let busy = false;
        const update = () => {
            const count = boxes.filter((box) => box.checked).length;
            if (button) button.disabled = busy || count === 0 || count > 500;
            if (status) status.textContent = count + ' selecionado(s)' + (count > 500 ? ' — máximo 500 por exclusão' : '');
            if (all) {
                all.checked = boxes.length > 0 && count === boxes.length;
                all.indeterminate = count > 0 && count < boxes.length;
            }
            return count;
        };
        all?.addEventListener('change', () => { boxes.forEach((box) => { box.checked = all.checked; }); update(); });
        boxes.forEach((box) => box.addEventListener('change', update));
        window.addEventListener('pageshow', update);
        update();
        if (typeof HTMLDialogElement === 'undefined' || typeof HTMLDialogElement.prototype.showModal !== 'function') {
            form.addEventListener('submit', (event) => { const count = update(); if (!count || count > 500) event.preventDefault(); });
            return;
        }
        if (!document.querySelector('[data-newsic-delete-style]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet'; link.href = '/assets/newsic-delete-modal.css?v=1';
            link.setAttribute('data-newsic-delete-style', ''); document.head.append(link);
        }
        const titleId = 'newsic-delete-title-' + index++;
        const dialog = document.createElement('dialog');
        dialog.className = 'newsic-delete-modal';
        dialog.setAttribute('aria-labelledby', titleId);
        dialog.innerHTML = '<div class="panel-heading"><h2>Confirmar exclusão de ramais</h2><button type="button" class="icon-button" data-modal-close aria-label="Fechar confirmação">×</button></div><div class="modal-content"><p class="modal-context"></p><p class="notice notice-warning">A exclusão é definitiva e remove os registros e suas senhas do NewDOC, não do equipamento Newsic. A recuperação dependerá de backup anterior.</p><p data-modal-message role="status" aria-live="polite"></p><ul class="modal-list" hidden></ul><form><label class="checkbox-label"><input type="checkbox" required> Entendo e confirmo a exclusão definitiva dos ramais listados.</label><div class="modal-actions"><button type="button" class="button button-quiet" data-modal-close>Cancelar</button><button type="submit" class="button button-danger" disabled>Excluir definitivamente</button></div></form></div>';
        dialog.querySelector('h2').id = titleId;
        document.body.append(dialog);
        const context = dialog.querySelector('.modal-context');
        const list = dialog.querySelector('.modal-list');
        const message = dialog.querySelector('[data-modal-message]');
        const confirmForm = dialog.querySelector('form');
        const checkbox = confirmForm.querySelector('input');
        const confirmButton = confirmForm.querySelector('button[type="submit"]');
        const closers = Array.from(dialog.querySelectorAll('[data-modal-close]'));
        const csrf = form.querySelector('input[name="_csrf"]')?.value;
        const endpoint = new URL(form.getAttribute('action') || '', location.origin);
        let token = '', controller = null, committing = false, expiryTimer = null, version = 0;
        const request = async (body, signal) => {
            if (!csrf || endpoint.origin !== location.origin || !/^\/newsic\/[1-9][0-9]*\/ramais\/excluir$/.test(endpoint.pathname)) throw new Error('Sessão ou endereço inválido. Atualize a página.');
            const response = await fetch(endpoint, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                headers: { Accept: 'application/json' }, body, signal,
            });
            if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Resposta indisponível (HTTP ' + response.status + '). Verifique sua sessão e atualize a lista.');
            const data = await response.json();
            if (!response.ok || data.success !== true) throw new Error(typeof data.error === 'string' ? data.error : 'Não foi possível concluir a solicitação.');
            return data;
        };
        const close = () => { if (!committing) dialog.close(); };
        closers.forEach((control) => control.addEventListener('click', close));
        dialog.addEventListener('cancel', (event) => { if (committing) event.preventDefault(); });
        dialog.addEventListener('close', () => {
            version++; controller?.abort(); controller = null;
            clearTimeout(expiryTimer);
            const oldToken = token;
            token = ''; checkbox.checked = false; confirmButton.disabled = true;
            list.replaceChildren(); list.hidden = true; context.textContent = ''; message.textContent = '';
            busy = false; update(); button?.focus();
            if (oldToken) {
                const body = new URLSearchParams({ _csrf: csrf, action: 'cancel', draft_token: oldToken });
                void request(body).catch(() => {});
            }
        });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const count = update();
            if (busy || !count || count > 500) return;
            const body = new FormData(form);
            const currentVersion = ++version;
            busy = true; update(); checkbox.checked = false; checkbox.disabled = true;
            confirmButton.disabled = true; token = ''; list.replaceChildren(); list.hidden = true;
            context.textContent = ''; message.textContent = 'Conferindo os ramais selecionados...';
            controller = new AbortController();
            dialog.showModal();
            closers[0]?.focus();
            try {
                const data = await request(body, controller.signal);
                if (currentVersion !== version || !dialog.open) return;
                if (!Array.isArray(data.rows) || !data.rows.length || typeof data.token !== 'string' || !data.instance || !Number.isFinite(data.expires)) throw new Error('Prévia inválida. Feche e tente novamente.');
                token = data.token;
                context.textContent = 'Empresa: ' + data.instance.company_name + ' (#' + data.instance.company_id + ') · Servidor: ' + data.instance.server_name + ' (#' + data.instance.id + ')';
                message.textContent = data.rows.length + ' ramal(is). Confira a lista antes de confirmar.';
                data.rows.forEach((row) => { const item = document.createElement('li'); item.textContent = 'Ramal ' + row.extension_number + ' · ID ' + row.id; list.append(item); });
                list.hidden = false; checkbox.disabled = false;
                expiryTimer = setTimeout(() => { token = ''; confirmButton.disabled = true; checkbox.disabled = true; message.textContent = 'Confirmação expirada. Feche e selecione os ramais novamente.'; }, Math.max(0, data.expires * 1000 - Date.now()));
            } catch (error) {
                if (currentVersion === version && dialog.open) message.textContent = error instanceof Error ? error.message : 'Não foi possível preparar a exclusão.';
            }
        });
        checkbox.addEventListener('change', () => { confirmButton.disabled = !token || !checkbox.checked || committing; });
        confirmForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!token || !checkbox.checked || committing) return;
            committing = true; confirmButton.disabled = true; checkbox.disabled = true;
            closers.forEach((control) => { control.disabled = true; });
            clearTimeout(expiryTimer);
            const providedToken = token; token = '';
            message.textContent = 'Processando exclusão...';
            try {
                await request(new URLSearchParams({ _csrf: csrf, action: 'confirm', draft_token: providedToken, confirm_delete: '1' }));
                committing = false;
                closers.forEach((control) => { control.disabled = false; });
                dialog.close();
                location.reload();
            } catch {
                message.textContent = 'Não foi possível confirmar o resultado. Feche e atualize a lista antes de tentar novamente.';
                committing = false;
                closers.forEach((control) => { control.disabled = false; });
            }
        });
        window.addEventListener('pagehide', () => { if (!committing) controller?.abort(); });
    });
})();
