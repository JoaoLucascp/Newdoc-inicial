<?php

use NewDoc\Csrf;

$isEdit = $mode === 'edit';
$title = $isEdit ? 'Editar instância Newbot' : 'Nova instância Newbot';
$active = 'newbot';
$action = $isEdit ? '/newbot/' . (int) ($instance['id'] ?? 0) : '/empresas/' . (int) $company['id'] . '/newbot';
$statuses = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'];
?>
<div class="page-heading"><div><a class="back-link" href="<?= $isEdit ? '/newbot/' . h($instance['id']) : '/empresas/' . h($company['id']) . '/newbot' ?>">← Voltar</a>
    <span class="eyebrow">NEWBOT · <?= $isEdit ? 'ATUALIZAÇÃO' : 'NOVO CADASTRO' ?></span><h1><?= h($title) ?></h1>
    <p class="page-subtitle"><?= h($company['name']) ?> · empresa #<?= h($company['id']) ?></p></div></div>
<form action="<?= h($action) ?>" method="post" class="form-layout"><input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
    <section class="panel form-section"><div class="panel-heading"><div><h2>Instância</h2><p>Identificação e situação operacional</p></div><span class="section-number">01</span></div><div class="form-grid">
        <label class="form-field" for="instance_name"><span>Nome identificador</span><input class="form-input" id="instance_name" name="instance_name" maxlength="160" value="<?= h($instance['instance_name'] ?? '') ?>">
            <?php if (isset($errors['instance_name'])): ?><small class="field-error"><?= h($errors['instance_name']) ?></small><?php endif; ?></label>
        <label class="form-field" for="status"><span>Status *</span><select class="form-select" id="status" name="status" required><?php foreach ($statuses as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= ($instance['status'] ?? 'ativo') === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
            <?php if (isset($errors['status'])): ?><small class="field-error"><?= h($errors['status']) ?></small><?php endif; ?></label>
        <label class="form-field field-wide" for="notes"><span>Observações</span><textarea class="form-input" id="notes" name="notes" rows="4" maxlength="2000"><?= h($instance['notes'] ?? '') ?></textarea>
            <?php if (isset($errors['notes'])): ?><small class="field-error"><?= h($errors['notes']) ?></small><?php endif; ?></label>
    </div></section>
    <section class="panel form-section"><div class="panel-heading"><div><h2>Documento de origem</h2><p>Vínculo manual; nenhum dado é importado automaticamente.</p></div><span class="section-number">02</span></div><div class="form-grid">
        <label class="form-field field-wide" for="source_document_id"><span>Documento Newbot</span><select class="form-select" id="source_document_id" name="source_document_id"><option value="">Sem vínculo</option>
        <?php foreach ($documents as $document): ?><option value="<?= h($document['id']) ?>" <?= (string) ($instance['source_document_id'] ?? '') === (string) $document['id'] ? 'selected' : '' ?>>#<?= h($document['id']) ?> · <?= h($document['original_filename']) ?><?= $document['company_id'] === null ? ' · sem empresa' : '' ?></option><?php endforeach; ?>
        </select><?php if (isset($errors['source_document_id'])): ?><small class="field-error"><?= h($errors['source_document_id']) ?></small><?php endif; ?></label>
    </div></section>
    <div class="form-actions form-wide"><a class="button button-quiet" href="<?= $isEdit ? '/newbot/' . h($instance['id']) : '/empresas/' . h($company['id']) . '/newbot' ?>">Cancelar</a>
        <button class="button button-primary" type="submit"><?= $isEdit ? 'Salvar alterações' : 'Cadastrar instância' ?></button></div>
</form>
