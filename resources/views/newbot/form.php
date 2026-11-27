<?php
use NewDoc\Csrf;
$isEdit = $mode === 'edit';
$title = $isEdit ? 'Editar instância Newbot' : 'Nova Instância Newbot';
$active = 'newbot';
$action = $isEdit ? '/newbot/' . (int) ($instance['id'] ?? 0) : '/empresas/' . (int) $company['id'] . '/newbot';
$backUrl = $isEdit ? '/newbot/' . (int) ($instance['id'] ?? 0) : '/empresas/' . (int) $company['id'] . '/newbot';
$statuses = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'];
?>
<link rel="stylesheet" href="/assets/newbot-form.css?v=2">
<script src="/assets/newbot-form.js?v=2" defer></script>
<div class="nb-page">
<nav class="nb-crumbs" aria-label="Trilha de navegação"><a href="/">Início</a><span aria-hidden="true">›</span><a href="/newbot">Newbot</a><span aria-hidden="true">›</span><a href="/empresas/<?= h($company['id']) ?>/newbot">Instâncias</a><span aria-hidden="true">›</span><span aria-current="page"><?= $isEdit ? 'Editar Instância' : 'Nova Instância' ?></span></nav>
<header class="nb-head"><span class="nb-robot" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M9 14h.01M15 14h.01M9.5 17.5h5"/><circle cx="12" cy="3" r="1"/></svg></span><div><h1><?= h($title) ?></h1><p><?= h($company['name']) ?> · Cadastre e gerencie a instância do Newbot.</p></div></header>
<form action="<?= h($action) ?>" method="post">
<input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
<section class="nb-card" aria-labelledby="nb-data-title"><div class="nb-card-head"><div><h2 id="nb-data-title">Dados da Instância</h2><p>Identificação e situação operacional do robô.</p></div><span class="nb-tag">Newbot</span></div>
<div class="nb-grid">
<label class="nb-field" for="nb-id"><span>ID interno do Newdoc</span><input class="nb-input" id="nb-id" value="<?= $isEdit ? h((string) ($instance['id'] ?? '')) : 'Gerado ao salvar' ?>" readonly><small>Identificador técnico gerado pelo Newdoc.</small></label>
<label class="nb-field" for="instance_name"><span>Nome da Instância</span><input class="nb-input" id="instance_name" name="instance_name" maxlength="160" value="<?= h($instance['instance_name'] ?? '') ?>" placeholder="Ex.: Atendimento Comercial"><?php if (isset($errors['instance_name'])): ?><small class="nb-error"><?= h($errors['instance_name']) ?></small><?php endif; ?></label>
<label class="nb-field" for="status"><span>Status <em>*</em></span><select class="nb-select" id="status" name="status" required><?php foreach ($statuses as $key => $label): ?><option value="<?= h($key) ?>" <?= ($instance['status'] ?? 'ativo') === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select><?php if (isset($errors['status'])): ?><small class="nb-error"><?= h($errors['status']) ?></small><?php endif; ?></label>
<label class="nb-field" for="source_document_id"><span>Documento de Origem</span><select class="nb-select" id="source_document_id" name="source_document_id"><option value="">Sem vínculo</option><?php foreach ($documents as $document): ?><option value="<?= h($document['id']) ?>" <?= (string) ($instance['source_document_id'] ?? '') === (string) $document['id'] ? 'selected' : '' ?>>#<?= h($document['id']) ?> · <?= h($document['original_filename']) ?><?= $document['company_id'] === null ? ' · sem empresa' : '' ?></option><?php endforeach; ?></select><?php if (isset($errors['source_document_id'])): ?><small class="nb-error"><?= h($errors['source_document_id']) ?></small><?php endif; ?></label>
<label class="nb-field" for="instance_code"><span>ID da Instância</span><input class="nb-input" id="instance_code" name="instance_code" maxlength="160" value="<?= h($instance['instance_code'] ?? '') ?>" placeholder="Ex.: newtel-comercial"><?php if (isset($errors['instance_code'])): ?><small class="nb-error"><?= h($errors['instance_code']) ?></small><?php endif; ?></label>
<label class="nb-field" for="access_link"><span>Link de acesso</span><input class="nb-input" id="access_link" name="access_link" type="url" maxlength="500" value="<?= h($instance['access_link'] ?? '') ?>" placeholder="https://"><?php if (isset($errors['access_link'])): ?><small class="nb-error"><?= h($errors['access_link']) ?></small><?php endif; ?></label><label class="nb-field nb-wide" for="notes"><span>Observações</span><textarea class="nb-textarea" id="notes" name="notes" rows="4" maxlength="2000" data-nb-counter="nb-count" aria-describedby="nb-count"><?= h($instance['notes'] ?? '') ?></textarea><small class="nb-counter" id="nb-count"><?= h((string) mb_strlen((string) ($instance['notes'] ?? ''))) ?>/2000</small><?php if (isset($errors['notes'])): ?><small class="nb-error"><?= h($errors['notes']) ?></small><?php endif; ?></label>
</div></section>
<div class="nb-actions"><a class="nb-btn nb-btn-quiet" href="<?= h($backUrl) ?>">Voltar</a><button class="nb-btn nb-btn-primary" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg><?= $isEdit ? 'Salvar alterações' : 'Salvar Instância' ?></button></div>
</form></div>
