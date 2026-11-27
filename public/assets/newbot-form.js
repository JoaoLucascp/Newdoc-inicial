'use strict';
document.querySelectorAll('[data-nb-counter]').forEach(function (field) {
    var output = document.getElementById(field.dataset.nbCounter);
    if (!output) return;
    function sync() {
        output.textContent = Array.from(field.value).length + '/' + field.maxLength;
    }
    field.addEventListener('input', sync);
    sync();
});
