<?php

use NewDoc\Csrf;

$title = 'Cadastrar lote de ramais';
$active = 'newsic';
$batchUrl = '/newsic/' . (int) $instance['id'] . '/ramais/lote';
$previewColumns = [
    'extension_number' => 'Ramal', 'has_password' => 'Senha', 'ip_address' => 'IP',
    'user_name' => 'Usuário', 'department' => 'Departamento', 'call_recording' => 'Gravação',
    'permission_lof' => 'LOF', 'permission_loc' => 'LOC', 'permission_ddf' => 'DDF',
    'permission_ddc' => 'DDC', 'permission_ddi' => 'DDI', 'permission_srv' => 'SRV',
    'status' => 'Status', 'notes' => 'Observações',
];
?>
<div class="page-heading"><div>
    <a class="back-link" href="/newsic/<?= h($instance['id']) ?>#ramais">← Voltar aos ramais</a>
    <span class="eyebrow">NEWSIC / IPBX · EMPRESA #<?= h($instance['company_id']) ?> · SERVIDOR #<?= h($instance['id']) ?></span>
    <h1>Cadastrar lote de ramais</h1>
    <p class="page-subtitle"><?= h($instance['company_name']) ?> · <?= h($instance['server_name']) ?></p>
</div><a class="button button-secondary" href="<?= h($batchUrl) ?>?modelo=csv">Baixar modelo CSV</a></div>
<?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<div class="notice notice-info"><span>Envie CSV UTF-8 separado por ponto e vírgula ou vírgula, com até 2 MiB e 1.000 ramais. A coluna Ramal é obrigatória. Use Sim/Não ou 1/0 nas permissões. Ramais existentes não serão sobrescritos. Esta etapa aceita somente CSV; XLSX será disponibilizado depois.</span></div>
<section class="panel">
    <div class="panel-heading"><div><h2>Enviar arquivo</h2><p>O envio prepara a prévia; não grava os ramais.</p></div></div>
    <form action="<?= h($batchUrl) ?>" method="post" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
        <input type="hidden" name="action" value="preview">
        <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
        <label class="form-field field-wide" for="batch"><span>Arquivo CSV</span><input class="form-input file-input" id="batch" type="file" name="batch" accept=".csv,text/csv" required></label>
        <div class="form-actions form-wide"><button class="button button-primary" type="submit">Gerar prévia</button></div>
    </form>
</section>
<?php if ($rows !== []): ?>
<section class="panel">
    <div class="panel-heading"><div><h2>Conferir <?= h(count($rows)) ?> ramal(is)</h2><p>Prévia válida até <?= h(date('H:i:s', $expires)) ?>. Nenhum ramal foi gravado ainda.</p></div></div>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Prévia dos ramais">
        <table class="data-table"><thead><tr><?php foreach ($previewColumns as $label): ?><th scope="col"><?= h($label) ?></th><?php endforeach; ?></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr><?php foreach ($previewColumns as $key => $label): ?><td>
            <?php if ($key === 'has_password'): ?><?= !empty($row[$key]) ? 'Informada' : 'Não informada' ?>
            <?php elseif ($key === 'call_recording' || str_starts_with($key, 'permission_')): ?><?= (int) $row[$key] === 1 ? 'Sim' : 'Não' ?>
            <?php else: ?><?= h((string) ($row[$key] ?? '') !== '' ? $row[$key] : '—') ?><?php endif; ?>
        </td><?php endforeach; ?></tr><?php endforeach; ?>
        </tbody></table>
    </div>
    <form action="<?= h($batchUrl) ?>" method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
        <input type="hidden" name="action" value="confirm">
        <input type="hidden" name="draft_token" value="<?= h($token) ?>">
        <label class="checkbox-label form-wide"><input type="checkbox" name="confirm_import" value="1" required> Confirmo o cadastro destes <?= h(count($rows)) ?> ramais na empresa <?= h($instance['company_name']) ?>, servidor <?= h($instance['server_name']) ?>.</label>
        <div class="form-actions form-wide"><button class="button button-primary" type="submit">Confirmar importação</button></div>
    </form>
    <form action="<?= h($batchUrl) ?>" method="post" class="filter-bar">
        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
        <input type="hidden" name="action" value="cancel">
        <button class="button button-quiet" type="submit">Descartar prévia e voltar</button>
    </form>
</section>
<?php endif; ?>
