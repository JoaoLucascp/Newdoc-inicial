<?php

use NewDoc\Csrf;

$title = 'Confirmar exclusão de ramais';
$active = 'newsic';
$deleteUrl = '/newsic/' . (int) $instance['id'] . '/ramais/excluir';
?>
<div class="page-heading"><div>
    <a class="back-link" href="/newsic/<?= h($instance['id']) ?>#ramais">← Voltar aos ramais sem excluir</a>
    <span class="eyebrow">EMPRESA #<?= h($instance['company_id']) ?> · SERVIDOR #<?= h($instance['id']) ?></span>
    <h1>Confirmar exclusão de ramais</h1>
    <p class="page-subtitle"><?= h($instance['company_name']) ?> · <?= h($instance['server_name']) ?></p>
</div></div>
<?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if ($rows !== []): ?>
<div class="notice notice-warning" role="alert"><span>A exclusão é definitiva: os <?= h(count($rows)) ?> ramais abaixo e suas senhas cadastradas serão removidos do NewDOC. Não é uma inativação. Não altera o equipamento Newsic. A recuperação dos registros dependerá de um backup anterior. Nenhum ramal foi excluído ainda.</span></div>
<section class="panel">
    <div class="panel-heading"><div><h2><?= h(count($rows)) ?> ramal(is) selecionado(s)</h2><p>Confirmação válida até <?= h(date('H:i:s', $expires)) ?>.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">ID</th><th scope="col">Ramal</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr><td><?= h($row['id']) ?></td><td><?= h($row['extension_number']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <form action="<?= h($deleteUrl) ?>" method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
        <input type="hidden" name="action" value="confirm">
        <input type="hidden" name="draft_token" value="<?= h($token) ?>">
        <label class="checkbox-label form-wide"><input type="checkbox" name="confirm_delete" value="1" required> Entendo que esta ação apagará definitivamente estes <?= h(count($rows)) ?> ramais da empresa <?= h($instance['company_name']) ?>, servidor <?= h($instance['server_name']) ?>.</label>
        <div class="form-actions form-wide"><button class="button button-secondary" type="submit">Excluir definitivamente <?= h(count($rows)) ?> ramal(is)</button></div>
    </form>
    <form action="<?= h($deleteUrl) ?>" method="post" class="filter-bar">
        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
        <input type="hidden" name="action" value="cancel">
        <button class="button button-quiet" type="submit">Cancelar exclusão</button>
    </form>
</section>
<?php endif; ?>
