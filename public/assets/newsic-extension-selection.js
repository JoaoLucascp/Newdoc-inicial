'use strict';
document.querySelectorAll('.extensions-panel').forEach((panel) => {
    const form = panel.querySelector('[data-extension-delete-form]');
    if (!form) return;
    const boxes = Array.from(panel.querySelectorAll('[data-extension-select]'));
    const all = panel.querySelector('[data-extension-select-all]');
    const button = form.querySelector('button[type="submit"]');
    const status = form.querySelector('[data-extension-selection-status]');
    const update = () => {
        const count = boxes.filter((box) => box.checked).length;
        if (button) button.disabled = count === 0 || count > 500;
        if (status) status.textContent = count + ' selecionado(s)' + (count > 500 ? ' — máximo 500 por exclusão' : '');
        if (all) {
            all.checked = boxes.length > 0 && count === boxes.length;
            all.indeterminate = count > 0 && count < boxes.length;
        }
        return count;
    };
    all?.addEventListener('change', () => {
        boxes.forEach((box) => { box.checked = all.checked; });
        update();
    });
    boxes.forEach((box) => box.addEventListener('change', update));
    form.addEventListener('submit', (event) => {
        const count = update();
        if (count === 0 || count > 500) event.preventDefault();
    });
    window.addEventListener('pageshow', update);
    update();
});
