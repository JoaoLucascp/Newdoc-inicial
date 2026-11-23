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
<section class="panel">
    <div class="panel-heading"><h2>Confirmar exportação confidencial</h2></div>
    <div class="notice notice-warning" role="note">
        O arquivo conterá senhas web, SSH e dos ramais em texto legível, quando cadastradas. Depois do download, o NewDOC não controla cópias ou compartilhamentos. Guarde-o em local protegido e não o envie a pessoas não autorizadas. A geração será auditada sem registrar os valores das senhas.
    </div>
    <?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <p>Inclui os dados cadastrados deste servidor, ramais, troncos, URA, roteamentos e redes. Identifica a empresa por nome e ID. Não consulta o equipamento Newsic nem incorpora arquivos anexos.</p>
    <p>No CSV, as colunas são seção, registro, campo e valor. Valores com risco de fórmula recebem um apóstrofo de proteção, inclusive senhas nessa condição. Este relatório não é modelo de importação. O PDF não aplica esse prefixo.</p>
    <form action="/newsic/<?= h($instance['id']) ?>/exportar" method="post" class="form-grid" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
        <label>Formato
            <select name="format" required>
                <option value="pdf" <?= $format === 'pdf' ? 'selected' : '' ?>>PDF</option>
                <option value="csv" <?= $format === 'csv' ? 'selected' : '' ?>>CSV UTF-8</option>
            </select>
        </label>
        <label class="checkbox-label form-wide"><input type="checkbox" name="confirm_secrets" value="1" required> Autorizo a exportação e entendo que o arquivo conterá senhas legíveis.</label>
        <div class="form-actions form-wide">
            <a class="button button-secondary" href="/newsic/<?= h($instance['id']) ?>">Cancelar</a>
            <button class="button button-primary" type="submit">Gerar e baixar arquivo confidencial</button>
        </div>
    </form>
</section>
