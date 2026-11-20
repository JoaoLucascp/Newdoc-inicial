<?php

use NewDoc\Auth;
use NewDoc\Csrf;

if (!Auth::canManage()) {
    return;
}

$readonlyId = 'credential-' . $credentialTarget . '-' . (int) $credentialId;
?>
<form class="form-field" method="post" action="<?= h('/credenciais/' . $credentialTarget . '/' . (int) $credentialId . '/revelar') ?>" aria-label="<?= h($credentialLabel) ?>">
    <button type="submit" hidden disabled aria-hidden="true" tabindex="-1"></button>
    <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
    <input class="form-input" id="<?= h($readonlyId) ?>" type="password" readonly autocomplete="off" placeholder="********" aria-label="<?= h($credentialLabel) ?>">
    <?php require __DIR__ . '/credential.php'; ?>
</form>
