<?php

use NewDoc\Auth;
use NewDoc\Csrf;

if (!Auth::canManage()) {
    return;
}
?>
<span data-credential data-credential-url="<?= h('/credenciais/' . $credentialTarget . '/' . (int) $credentialId . '/revelar') ?>">
    <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
    <button class="icon-button" type="button" data-credential-toggle aria-pressed="false" aria-label="Mostrar senha" title="Mostrar senha">
        <svg data-eye-closed width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 3 18 18M10 5a11 11 0 0 1 11 7 15 15 0 0 1-4 5M6 6a15 15 0 0 0-3 6s3 7 9 7a12 12 0 0 0 5-1M10 10a3 3 0 0 0 4 4"/></svg>
        <svg data-eye-open hidden width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
    </button>
    <code data-credential-value hidden></code>
    <span data-credential-message role="status"></span>
</span>
