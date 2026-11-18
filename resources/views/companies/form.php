<?php

use NewDoc\Csrf;

$isEdit = $mode === 'edit';
$title = $isEdit ? 'Editar empresa' : 'Nova empresa';
$active = 'companies';
$action = $isEdit ? '/empresas/' . (int) ($company['id'] ?? 0) : '/empresas';
$textFields = [
    'name' => ['Nome da empresa', true],
    'trade_name' => ['Nome fantasia', false],
    'cnpj' => ['CNPJ', false],
    'responsible_name' => ['Nome do responsável', false],
    'responsible_phone' => ['Telefone do responsável', false],
    'email' => ['E-mail principal', false],
    'address' => ['Endereço', false],
];
?>
<div class="page-heading">
    <div><a class="back-link" href="<?= $isEdit ? '/empresas/' . h($company['id']) : '/empresas' ?>">← Voltar para empresas</a>
        <span class="eyebrow"><?= $isEdit ? 'ATUALIZAÇÃO CADASTRAL' : 'NOVO CADASTRO' ?></span>
        <h1><?= h($title) ?></h1><p class="page-subtitle">Campos opcionais podem ser preenchidos posteriormente.</p></div>
</div>
<form action="<?= h($action) ?>" method="post" class="form-layout">
    <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
    <?php if ($duplicateWarning && isset($errors['confirm_duplicate'])): ?>
        <div class="alert alert-warning form-wide" role="alert">
            <strong>Possível empresa homônima</strong>
            <p><?= h($errors['confirm_duplicate']) ?> Cada cadastro continua com seu próprio ID interno.</p>
            <label class="checkbox-label"><input type="checkbox" name="confirm_duplicate" value="1" required>
                Revisei e confirmo que se trata de outro cadastro</label>
        </div>
    <?php endif; ?>
    <section class="panel form-section">
        <div class="panel-heading"><div><h2>Dados da empresa</h2><p>Identificação e contato principal</p></div><span class="section-number">01</span></div>
        <div class="form-grid">
            <?php foreach ($textFields as $field => [$label, $required]): ?>
                <label class="form-field <?= in_array($field, ['address'], true) ? 'field-wide' : '' ?>" for="<?= h($field) ?>">
                    <span><?= h($label) ?><?= $required ? ' *' : '' ?></span>
                    <?php if ($field === 'address'): ?>
                        <textarea class="form-input" id="<?= h($field) ?>" name="<?= h($field) ?>" rows="3" maxlength="500"><?= h($company[$field] ?? '') ?></textarea>
                    <?php else: ?>
                        <input class="form-input" id="<?= h($field) ?>" name="<?= h($field) ?>"
                               type="<?= $field === 'email' ? 'email' : 'text' ?>"
                               maxlength="<?= $field === 'email' ? '254' : ($field === 'name' || $field === 'trade_name' ? '200' : '160') ?>"
                               value="<?= h($company[$field] ?? '') ?>" <?= $required ? 'required' : '' ?>>
                    <?php endif; ?>
                    <?php if (isset($errors[$field])): ?><small class="field-error"><?= h($errors[$field]) ?></small><?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="panel form-section">
        <div class="panel-heading"><div><h2>Contrato</h2><p>Situação e período contratual</p></div><span class="section-number">02</span></div>
        <div class="form-grid">
            <label class="form-field" for="contract_status"><span>Situação do contrato *</span>
                <select class="form-select" id="contract_status" name="contract_status" required>
                    <?php foreach (['ativo' => 'Ativo', 'inativo' => 'Inativo', 'inexistente' => 'Inexistente'] as $key => $label): ?>
                        <option value="<?= h($key) ?>" <?= ($company['contract_status'] ?? 'inexistente') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form-field" for="contract_start_date"><span>Início do contrato</span>
                <input class="form-input" id="contract_start_date" name="contract_start_date" type="date" value="<?= h($company['contract_start_date'] ?? '') ?>">
                <?php if (isset($errors['contract_start_date'])): ?><small class="field-error"><?= h($errors['contract_start_date']) ?></small><?php endif; ?>
            </label>
            <label class="form-field" for="contract_end_date"><span>Fim do contrato</span>
                <input class="form-input" id="contract_end_date" name="contract_end_date" type="date" value="<?= h($company['contract_end_date'] ?? '') ?>">
                <?php if (isset($errors['contract_end_date'])): ?><small class="field-error"><?= h($errors['contract_end_date']) ?></small><?php endif; ?>
            </label>
            <label class="form-field field-wide" for="notes"><span>Observações</span>
                <textarea class="form-input" id="notes" name="notes" rows="3" maxlength="2000"><?= h($company['notes'] ?? '') ?></textarea>
                <small class="field-help">Não registre senhas, tokens ou outras credenciais neste campo.</small>
                <?php if (isset($errors['notes'])): ?><small class="field-error"><?= h($errors['notes']) ?></small><?php endif; ?>
            </label>
        </div>
    </section>
    <div class="form-actions form-wide">
        <a class="button button-quiet" href="<?= $isEdit ? '/empresas/' . h($company['id']) : '/empresas' ?>">Cancelar</a>
        <button class="button button-primary" type="submit"><?= $isEdit ? 'Salvar alterações' : 'Cadastrar empresa' ?></button>
    </div>
</form>
