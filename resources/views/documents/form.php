<?php

use NewDoc\Csrf;

$title = 'Registrar documento';
$active = 'documents';
$values = $values ?? [];
$typeLabels = ['newsic' => 'Newsic', 'newbot' => 'Newbot', 'newvoz' => 'Newvoz', 'contatos' => 'Contatos', 'outro' => 'Outro'];
?>
<div class="page-heading">
    <div><a class="back-link" href="/documentos">← Voltar para documentos</a><span class="eyebrow">NOVO DOCUMENTO</span>
        <h1>Registrar documento</h1><p class="page-subtitle">O vínculo com uma empresa é opcional e deve ser confirmado manualmente.</p></div>
</div>
<form action="/documentos" method="post" enctype="multipart/form-data" class="form-layout">
    <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
    <section class="panel form-section">
        <div class="panel-heading"><div><h2>Arquivo de origem</h2><p>O original será preservado criptografado, sem extração automática de dados.</p></div><span class="section-number">01</span></div>
        <div class="form-grid">
            <label class="form-field field-wide" for="document"><span>Arquivo *</span>
                <input class="form-input file-input" id="document" name="document" type="file" accept=".xlsx,.pdf,.docx,.odt,.csv" required>
                <small class="field-help">Formatos aceitos: XLSX, PDF, DOCX, ODT e CSV. Limite <?= h(number_format($maxUploadBytes / 1024 / 1024, 0, ',', '.')) ?> MB.</small>
                <?php if (isset($errors['document'])): ?><small class="field-error"><?= h($errors['document']) ?></small><?php endif; ?>
            </label>
            <label class="form-field" for="document_type"><span>Tipo de documento *</span>
                <select class="form-select" id="document_type" name="document_type" required>
                    <option value="">Selecione</option><?php foreach ($typeLabels as $key => $label): ?>
                        <option value="<?= h($key) ?>" <?= ($values['document_type'] ?? '') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['document_type'])): ?><small class="field-error"><?= h($errors['document_type']) ?></small><?php endif; ?>
            </label>
            <label class="form-field" for="company_id"><span>Empresa vinculada</span>
                <select class="form-select" id="company_id" name="company_id">
                    <option value="">Sem vínculo confirmado</option><?php foreach ($companies as $company): ?>
                        <option value="<?= h($company['id']) ?>" <?= (string) ($values['company_id'] ?? '') === (string) $company['id'] ? 'selected' : '' ?>><?= h($company['name']) ?> · ID <?= h($company['id']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['company_id'])): ?><small class="field-error"><?= h($errors['company_id']) ?></small><?php endif; ?>
                <?php if (isset($errors['confirm_company_link'])): ?>
                    <small class="field-error"><?= h($errors['confirm_company_link']) ?></small>
                    <label class="checkbox-label"><input type="checkbox" name="confirm_company_link" value="1" required>
                        Confirmei a empresa pelo ID exibido na lista</label>
                <?php endif; ?>
            </label>
            <label class="form-field field-wide" for="notes"><span>Observações</span>
                <textarea class="form-input" id="notes" name="notes" rows="3" maxlength="2000"><?= h($values['notes'] ?? '') ?></textarea>
                <small class="field-help">Não registre senhas, tokens ou outras credenciais neste campo.</small>
                <?php if (isset($errors['notes'])): ?><small class="field-error"><?= h($errors['notes']) ?></small><?php endif; ?>
            </label>
        </div>
    </section>
    <div class="notice notice-warning form-wide"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2 21h20L12 3z"/><path d="M12 9v4M12 17h.01"/></svg>
        <span>O arquivo fica pendente e criptografado no armazenamento local. Esta fase não exibe, extrai nem importa os dados do arquivo. Configure <code>CREDENTIALS_KEY</code> antes de registrar documentos.</span>
    </div>
    <div class="form-actions form-wide"><a class="button button-quiet" href="/documentos">Cancelar</a>
        <button class="button button-primary" type="submit">Registrar com segurança</button></div>
</form>
