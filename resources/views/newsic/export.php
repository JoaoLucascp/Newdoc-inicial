<?php

use NewDoc\Csrf;

$title = 'Exportar Newsic · ' . $instance['server_name'];
$active = 'newsic';
?>
<div class="page-heading">
    <div>
        <a class="back-link" href="/newsic/<?= h($instance['id']) ?>">← Voltar ao servidor</a>
        <h1>Exportar documentação Newsic</h1>
        <p class="page-subtitle">Empresa: <?= h($instance['company_name']) ?> (#<?= h($instance['company_id']) ?>) · Servidor: <?= h($instance['server_name']) ?> (#<?= h($instance['id']) ?>)</p>
    </div>
</div>
<?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<section class="panel">
    <div class="panel-heading"><h2>Ficha técnica em PDF</h2></div>
    <form action="/newsic/<?= h($instance['id']) ?>/exportar" method="post" class="form-grid" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
        <input type="hidden" name="format" value="pdf">
        <div class="form-actions form-wide">
            <a class="button button-secondary" href="/newsic/<?= h($instance['id']) ?>">Cancelar</a>
            <button class="button button-primary" type="submit">Gerar PDF</button>
        </div>
    </form>
</section>
<section class="panel">
    <div class="panel-heading"><h2>CSV confidencial</h2></div>
    <div class="notice notice-warning" role="note">
        O CSV contém senhas legíveis. Guarde-o em local seguro e compartilhe apenas com pessoas autorizadas. A exportação é auditada, sem registrar as senhas.
    </div>
    <form action="/newsic/<?= h($instance['id']) ?>/exportar" method="post" class="form-grid" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
        <input type="hidden" name="format" value="csv">
        <label class="checkbox-label form-wide"><input type="checkbox" name="confirm_secrets" value="1" required> Autorizo a exportação e entendo que o arquivo conterá senhas legíveis.</label>
        <div class="form-actions form-wide">
            <button class="button button-secondary" type="submit">Gerar CSV confidencial</button>
        </div>
    </form>
</section>
