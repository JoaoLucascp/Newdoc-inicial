<?php

use NewDoc\Csrf;

$extensionFields = $section['fields'];
$newExtensionOpen = ($formState['section'] ?? null) === 'extensions' && ($formState['record_id'] ?? null) === null;
?>
<link rel="stylesheet" href="/assets/newsic-extensions.css?v=1">
<script src="/assets/newsic-extensions.js?v=1" defer></script>
<section class="panel extensions-panel" id="ramais">
    <div class="panel-heading"><div><h2>Ramais</h2><p><?= h($section['record_count']) ?> registro(s)</p></div><span class="section-number">02</span></div>
    <?php if ($canManage): ?>
        <div class="extensions-toolbar">
            <button type="button" class="button button-primary" data-extension-toggle aria-controls="extension-new" aria-expanded="<?= $newExtensionOpen ? 'true' : 'false' ?>">Novo ramal</button>
            <a class="button button-secondary" href="/newsic/<?= h($instance['id']) ?>#ramais">Atualizar lista</a>
        </div>
        <?php if ($section['records'] === []): ?><div class="empty-inline">Nenhum ramal cadastrado.</div><?php else: ?>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Lista de ramais; role horizontalmente para ver todos os campos">
                <table class="data-table extensions-table">
                    <thead><tr><?php foreach ($extensionFields as $extensionField): ?><th scope="col"><?= h($extensionField['label']) ?></th><?php endforeach; ?><th scope="col">Ações</th></tr></thead>
                    <?php foreach ($section['records'] as $record): ?>
                        <?php
                        $recordId = (int) $record['values']['id'];
                        $extensionValues = $record['values'];
                        $editOpen = ($formState['section'] ?? null) === 'extensions' && (int) ($formState['record_id'] ?? 0) === $recordId;
                        ?>
                        <tbody>
                            <tr class="extension-summary">
                                <?php foreach ($extensionFields as $extensionField): ?>
                                    <?php $extensionName = $extensionField['name']; $extensionValue = $extensionValues[$extensionName] ?? ''; ?>
                                    <td class="<?= $extensionField['type'] === 'secret' ? 'extension-secret' : ($extensionName === 'notes' ? 'extension-notes' : '') ?>">
                                        <?php if ($extensionField['type'] === 'secret'): ?>
                                            <?php if (!empty($record['secret_fields'][$extensionName])): ?>
                                                <?php
                                                $credentialTarget = 'newsic_extension';
                                                $credentialId = $recordId;
                                                $credentialLabel = 'Senha do ramal ' . $extensionValues['extension_number'];
                                                require dirname(__DIR__) . '/partials/credential_readonly.php';
                                                ?>
                                            <?php else: ?>Não configurada<?php endif; ?>
                                        <?php elseif ($extensionField['type'] === 'checkbox'): ?>
                                            <span class="status-badge <?= (int) $extensionValue === 1 ? 'status-ativo' : 'status-muted' ?>"><span></span><?= (int) $extensionValue === 1 ? 'Sim' : 'Não' ?></span>
                                        <?php elseif ($extensionName === 'status'): ?>
                                            <span class="status-badge status-<?= h($extensionValue) ?>"><span></span><?= h($statusLabels[$extensionValue] ?? $extensionValue) ?></span>
                                        <?php else: ?><?= h((string) $extensionValue !== '' ? $extensionValue : '—') ?><?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><button type="button" class="button button-secondary" data-extension-toggle aria-controls="extension-edit-<?= h($recordId) ?>" aria-expanded="<?= $editOpen ? 'true' : 'false' ?>" aria-label="Editar ramal <?= h($extensionValues['extension_number']) ?>">Editar</button></td>
                            </tr>
                            <tr class="extension-editor" id="extension-edit-<?= h($recordId) ?>" <?= $editOpen ? '' : 'hidden' ?>><td colspan="<?= h(count($extensionFields) + 1) ?>">
                                <h3 class="extension-editor-heading">Editar ramal <?= h($extensionValues['extension_number']) ?></h3>
                                <form action="/newsic/<?= h($instance['id']) ?>/dados" method="post" class="form-grid newsic-record-form">
                                    <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                                    <input type="hidden" name="section" value="extensions">
                                    <input type="hidden" name="record_id" value="<?= h($recordId) ?>">
                                    <?php
                                    $fields = $extensionFields;
                                    $values = $editOpen ? array_replace($extensionValues, $formState['values'] ?? []) : $extensionValues;
                                    $secretFields = $record['secret_fields'];
                                    $prefix = 'record-extensions-' . $recordId;
                                    require __DIR__ . '/_fields.php';
                                    ?>
                                    <div class="form-actions form-wide">
                                        <button type="button" class="button button-quiet" data-extension-toggle aria-controls="extension-edit-<?= h($recordId) ?>" aria-expanded="<?= $editOpen ? 'true' : 'false' ?>">Recolher</button>
                                        <button type="submit" class="button button-primary">Salvar registro</button>
                                    </div>
                                </form>
                            </td></tr>
                        </tbody>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
        <div id="extension-new" <?= $newExtensionOpen ? '' : 'hidden' ?>>
            <h3 class="extension-editor-heading">Novo ramal</h3>
            <form action="/newsic/<?= h($instance['id']) ?>/dados" method="post" class="form-grid newsic-record-form">
                <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                <input type="hidden" name="section" value="extensions">
                <input type="hidden" name="record_id" value="">
                <?php
                $fields = $extensionFields;
                $values = $newExtensionOpen ? ($formState['values'] ?? []) : [];
                $secretFields = [];
                $prefix = 'new-extensions';
                require __DIR__ . '/_fields.php';
                ?>
                <div class="form-actions form-wide"><button type="submit" class="button button-primary">Adicionar registro</button></div>
            </form>
        </div>
    <?php else: ?><div class="empty-inline">Dados técnicos restritos a técnicos e administradores.</div><?php endif; ?>
</section>
