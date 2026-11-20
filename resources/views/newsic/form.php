<?php

use NewDoc\Csrf;

$isEdit = $mode === 'edit';
$title = $isEdit ? 'Editar instância Newsic' : 'Nova instância Newsic';
$active = 'newsic';
$action = $isEdit ? '/newsic/' . (int) ($instance['id'] ?? 0) : '/empresas/' . (int) $company['id'] . '/newsic';
$statuses = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'];
$serverTypes = ['virtual' => 'Virtual', 'cloud' => 'Cloud', 'fisico' => 'Físico'];
?>
<div class="page-heading">
    <div><a class="back-link" href="<?= $isEdit ? '/newsic/' . h($instance['id']) : '/empresas/' . h($company['id']) . '/newsic' ?>">← Voltar</a>
        <span class="eyebrow">NEWSIC / IPBX · <?= $isEdit ? 'ATUALIZAÇÃO' : 'NOVO CADASTRO' ?></span>
        <h1><?= h($title) ?></h1><p class="page-subtitle"><?= h($company['name']) ?> · empresa #<?= h($company['id']) ?></p></div>
</div>
<?php if (isset($errors['status'])): ?><div class="alert alert-error" role="alert"><?= h($errors['status']) ?></div><?php endif; ?>
<form action="<?= h($action) ?>" method="post" class="form-layout">
    <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
    <section class="panel form-section">
        <div class="panel-heading"><div><h2>Servidor</h2><p>Identificação, rede e situação operacional</p></div><span class="section-number">01</span></div>
        <div class="form-grid">
            <label class="form-field" for="server_name"><span>Nome do servidor *</span>
                <input class="form-input" id="server_name" name="server_name" maxlength="160" required value="<?= h($instance['server_name'] ?? '') ?>">
                <?php if (isset($errors['server_name'])): ?><small class="field-error"><?= h($errors['server_name']) ?></small><?php endif; ?>
            </label>
            <label class="form-field" for="server_type"><span>Tipo</span>
                <select class="form-select" id="server_type" name="server_type"><option value="">Não informado</option>
                    <?php foreach ($serverTypes as $key => $label): ?><option value="<?= h($key) ?>" <?= ($instance['server_type'] ?? '') === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
                </select>
                <?php if (isset($errors['server_type'])): ?><small class="field-error"><?= h($errors['server_type']) ?></small><?php endif; ?>
            </label>
            <label class="form-field" for="internal_ip"><span>IP interno</span>
                <input class="form-input" id="internal_ip" name="internal_ip" maxlength="45" value="<?= h($instance['internal_ip'] ?? '') ?>">
                <?php if (isset($errors['internal_ip'])): ?><small class="field-error"><?= h($errors['internal_ip']) ?></small><?php endif; ?>
            </label>
            <label class="form-field" for="external_ip"><span>IP externo</span>
                <input class="form-input" id="external_ip" name="external_ip" maxlength="45" value="<?= h($instance['external_ip'] ?? '') ?>">
                <?php if (isset($errors['external_ip'])): ?><small class="field-error"><?= h($errors['external_ip']) ?></small><?php endif; ?>
            </label>
            <label class="form-field" for="software_version"><span>Versão do Newsic</span>
                <input class="form-input" id="software_version" name="software_version" maxlength="120" value="<?= h($instance['software_version'] ?? '') ?>">
                <?php if (isset($errors['software_version'])): ?><small class="field-error"><?= h($errors['software_version']) ?></small><?php endif; ?>
            </label>
            <label class="form-field" for="status"><span>Status *</span>
                <select class="form-select" id="status" name="status" required>
                    <?php foreach ($statuses as $key => $label): ?><option value="<?= h($key) ?>" <?= ($instance['status'] ?? 'ativo') === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="form-field" for="freemyip_link"><span>Link FreeMyIP</span>
                <input class="form-input" id="freemyip_link" name="freemyip_link" maxlength="500" value="<?= h($instance['freemyip_link'] ?? '') ?>">
                <?php if (isset($errors['freemyip_link'])): ?><small class="field-error"><?= h($errors['freemyip_link']) ?></small><?php endif; ?>
            </label>
            <label class="form-field" for="documentation_path"><span>Documentação</span>
                <input class="form-input" id="documentation_path" name="documentation_path" maxlength="500" value="<?= h($instance['documentation_path'] ?? '') ?>">
                <?php if (isset($errors['documentation_path'])): ?><small class="field-error"><?= h($errors['documentation_path']) ?></small><?php endif; ?>
            </label>
            <label class="form-field field-wide" for="notes"><span>Observações</span>
                <textarea class="form-input" id="notes" name="notes" rows="3" maxlength="2000"><?= h($instance['notes'] ?? '') ?></textarea>
                <?php if (isset($errors['notes'])): ?><small class="field-error"><?= h($errors['notes']) ?></small><?php endif; ?>
            </label>
        </div>
    </section>
    <section class="panel form-section">
        <div class="panel-heading"><div><h2>Acesso web</h2><p>Credenciais criptografadas. Consulta pelo olho para técnicos e administradores.</p></div><span class="section-number">02</span></div>
        <div class="form-grid">
            <label class="form-field" for="web_username"><span>Usuário web</span>
                <input class="form-input" id="web_username" name="web_username" maxlength="160" value="<?= h($instance['web_username'] ?? '') ?>">
            </label>
            <label class="form-field" for="web_port"><span>Porta web</span>
                <input class="form-input" id="web_port" name="web_port" type="number" min="1" max="65535" value="<?= h($instance['web_port'] ?? '') ?>">
                <?php if (isset($errors['web_port'])): ?><small class="field-error"><?= h($errors['web_port']) ?></small><?php endif; ?>
            </label>
            <div class="form-field">
                <label for="web_password"><span>Senha web</span></label>
                <input class="form-input" id="web_password" name="web_password" type="password" autocomplete="new-password">
                <small class="field-help"><?= !empty($instance['has_web_password']) ? 'Já configurada. Deixe em branco para manter. Use o olho para consultar a senha salva.' : 'Será criptografada antes de ser gravada.' ?></small>
                <?php if ($isEdit && !empty($instance['has_web_password']) && (int) ($instance['id'] ?? 0) > 0): ?>
                    <?php
                    $credentialTarget = 'newsic_web';
                    $credentialId = (int) $instance['id'];
                    require dirname(__DIR__) . '/partials/credential.php';
                    ?>
                <?php endif; ?>
                <?php if (!empty($instance['has_web_password'])): ?><label class="checkbox-label"><input type="checkbox" name="clear_web_password" value="1" <?= ($instance['clear_web_password'] ?? '') === '1' ? 'checked' : '' ?>> Remover a credencial</label><?php endif; ?>
                <?php if (isset($errors['web_password'])): ?><small class="field-error"><?= h($errors['web_password']) ?></small><?php endif; ?>
            </div>
        </div>
    </section>
    <section class="panel form-section">
        <div class="panel-heading"><div><h2>Acesso SSH</h2><p>Credenciais criptografadas. Consulta pelo olho para técnicos e administradores.</p></div><span class="section-number">03</span></div>
        <div class="form-grid">
            <label class="form-field" for="ssh_username"><span>Usuário SSH</span>
                <input class="form-input" id="ssh_username" name="ssh_username" maxlength="160" value="<?= h($instance['ssh_username'] ?? '') ?>">
            </label>
            <label class="form-field" for="ssh_port"><span>Porta SSH</span>
                <input class="form-input" id="ssh_port" name="ssh_port" type="number" min="1" max="65535" value="<?= h($instance['ssh_port'] ?? '') ?>">
                <?php if (isset($errors['ssh_port'])): ?><small class="field-error"><?= h($errors['ssh_port']) ?></small><?php endif; ?>
            </label>
            <div class="form-field field-wide">
                <label for="ssh_password"><span>Senha SSH</span></label>
                <input class="form-input" id="ssh_password" name="ssh_password" type="password" autocomplete="new-password">
                <small class="field-help"><?= !empty($instance['has_ssh_password']) ? 'Já configurada. Deixe em branco para manter. Use o olho para consultar a senha salva.' : 'Será criptografada antes de ser gravada.' ?></small>
                <?php if ($isEdit && !empty($instance['has_ssh_password']) && (int) ($instance['id'] ?? 0) > 0): ?>
                    <?php
                    $credentialTarget = 'newsic_ssh';
                    $credentialId = (int) $instance['id'];
                    require dirname(__DIR__) . '/partials/credential.php';
                    ?>
                <?php endif; ?>
                <?php if (!empty($instance['has_ssh_password'])): ?><label class="checkbox-label"><input type="checkbox" name="clear_ssh_password" value="1" <?= ($instance['clear_ssh_password'] ?? '') === '1' ? 'checked' : '' ?>> Remover a credencial</label><?php endif; ?>
                <?php if (isset($errors['ssh_password'])): ?><small class="field-error"><?= h($errors['ssh_password']) ?></small><?php endif; ?>
            </div>
        </div>
    </section>
    <section class="panel form-section">
        <div class="panel-heading"><div><h2>Documento de origem</h2><p>Vínculo manual; nenhum dado será importado.</p></div><span class="section-number">04</span></div>
        <div class="form-grid">
            <label class="form-field field-wide" for="source_document_id"><span>Documento Newsic</span>
                <select class="form-select" id="source_document_id" name="source_document_id">
                    <option value="">Sem vínculo</option>
                    <?php foreach ($documents as $document): ?>
                        <option value="<?= h($document['id']) ?>" <?= (string) ($instance['source_document_id'] ?? '') === (string) $document['id'] ? 'selected' : '' ?>>
                            #<?= h($document['id']) ?> · <?= h($document['original_filename']) ?><?= $document['company_id'] === null ? ' · sem empresa' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['source_document_id'])): ?><small class="field-error"><?= h($errors['source_document_id']) ?></small><?php endif; ?>
            </label>
        </div>
    </section>
    <div class="form-actions form-wide">
        <a class="button button-quiet" href="<?= $isEdit ? '/newsic/' . h($instance['id']) : '/empresas/' . h($company['id']) . '/newsic' ?>">Cancelar</a>
        <button class="button button-primary" type="submit"><?= $isEdit ? 'Salvar alterações' : 'Cadastrar instância' ?></button>
    </div>
</form>
