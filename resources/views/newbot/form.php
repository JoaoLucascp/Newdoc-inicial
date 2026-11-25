<?php

use NewDoc\Csrf;

$isEdit = $mode === 'edit';
$title = $isEdit ? 'Editar instância Newbot' : 'Nova Instância Newbot';
$active = 'newbot';
$action = $isEdit ? '/newbot/' . (int) ($instance['id'] ?? 0) : '/empresas/' . (int) $company['id'] . '/newbot';
$backUrl = $isEdit ? '/newbot/' . (int) ($instance['id'] ?? 0) : '/empresas/' . (int) $company['id'] . '/newbot';
$statuses = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'];
$notes = (string) ($instance['notes'] ?? '');
?>
<style>
.nb-page{--nb-navy:#102442;--nb-bg:#F4F6FA;--nb-gold:#C9A45B;--nb-green:#1E9E5A;--nb-line:#E3E8F0;--nb-muted:#6B7A90;font-family:'Inter','Poppins','Segoe UI',system-ui,sans-serif;color:var(--nb-navy)}
.nb-page *{box-sizing:border-box}
.nb-crumbs{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--nb-muted);margin-bottom:18px}
.nb-crumbs a{color:var(--nb-muted);text-decoration:none}
.nb-crumbs a:hover{color:var(--nb-navy)}
.nb-crumbs .sep{opacity:.55}
.nb-crumbs .now{color:var(--nb-navy);font-weight:600}
.nb-head{display:flex;align-items:center;gap:16px;margin-bottom:22px}
.nb-robot{width:52px;height:52px;border-radius:14px;background:var(--nb-navy);display:flex;align-items:center;justify-content:center;flex:0 0 52px;box-shadow:0 6px 16px rgba(16,36,66,.22)}
.nb-robot svg{width:26px;height:26px;stroke:#fff;fill:none;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.nb-head h1{margin:0;font-size:25px;font-weight:700;letter-spacing:-.4px;color:var(--nb-navy)}
.nb-head p{margin:4px 0 0;font-size:13.5px;color:var(--nb-muted)}
.nb-card{background:#fff;border:1px solid var(--nb-line);border-radius:12px;box-shadow:0 1px 2px rgba(16,36,66,.04),0 8px 24px rgba(16,36,66,.05);padding:24px 26px;margin-bottom:20px}
.nb-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding-bottom:16px;margin-bottom:20px;border-bottom:1px solid var(--nb-line)}
.nb-card-head h2{margin:0;font-size:15.5px;font-weight:700;color:var(--nb-navy)}
.nb-card-head p{margin:4px 0 0;font-size:12.5px;color:var(--nb-muted)}
.nb-tag{font-size:10.5px;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:var(--nb-gold);border:1px solid rgba(201,164,91,.45);background:rgba(201,164,91,.08);border-radius:6px;padding:4px 9px;white-space:nowrap}
.nb-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 22px}
.nb-field{display:flex;flex-direction:column;gap:7px}
.nb-field.nb-wide{grid-column:1/-1}
.nb-field>span{font-size:12.5px;font-weight:600;color:var(--nb-navy)}
.nb-field>span em{color:var(--nb-gold);font-style:normal;margin-left:2px}
.nb-input,.nb-select,.nb-textarea{width:100%;border:1px solid var(--nb-line);border-radius:10px;background:#fff;padding:11px 13px;font-size:13.5px;font-family:inherit;color:var(--nb-navy);transition:border-color .15s,box-shadow .15s}
.nb-input:focus,.nb-select:focus,.nb-textarea:focus{outline:none;border-color:var(--nb-navy);box-shadow:0 0 0 3px rgba(16,36,66,.1)}
.nb-input[readonly]{background:var(--nb-bg);color:var(--nb-muted);cursor:default}
.nb-textarea{resize:vertical;min-height:104px;line-height:1.55}
.nb-select{appearance:none;background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236B7A90' stroke-width='2' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;background-size:16px;padding-right:38px}
.nb-counter{display:block;text-align:right;font-size:11.5px;color:var(--nb-muted);margin-top:-2px}
.nb-error{font-size:12px;color:#B42318;font-weight:500}
.nb-actions{display:flex;align-items:center;justify-content:space-between;gap:16px;background:#fff;border:1px solid var(--nb-line);border-radius:12px;padding:16px 22px;box-shadow:0 1px 2px rgba(16,36,66,.04)}
.nb-btn{display:inline-flex;align-items:center;gap:9px;border-radius:10px;padding:11px 20px;font-size:13.5px;font-weight:600;font-family:inherit;text-decoration:none;cursor:pointer;border:1px solid transparent;transition:background .15s,border-color .15s,transform .1s}
.nb-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.nb-btn-quiet{background:#fff;border-color:var(--nb-line);color:var(--nb-navy)}
.nb-btn-quiet:hover{border-color:#C3CDDC;background:var(--nb-bg)}
.nb-btn-primary{background:var(--nb-navy);color:#fff;box-shadow:0 4px 12px rgba(16,36,66,.2)}
.nb-btn-primary:hover{background:#0A1A31}
.nb-btn-primary:active{transform:translateY(1px)}
@media (max-width:820px){.nb-grid{grid-template-columns:1fr}.nb-actions{flex-direction:column-reverse;align-items:stretch}.nb-btn{justify-content:center}}
</style>
<div class="nb-page">
    <nav class="nb-crumbs" aria-label="Trilha de navegação">
        <a href="/">Início</a><span class="sep">›</span>
        <a href="/empresas">Newbot</a><span class="sep">›</span>
        <a href="/empresas/<?= h($company['id']) ?>/newbot">Instâncias</a><span class="sep">›</span>
        <span class="now"><?= $isEdit ? 'Editar Instância' : 'Nova Instância' ?></span>
    </nav>

    <header class="nb-head">
        <span class="nb-robot" aria-hidden="true">
            <svg viewBox="0 0 24 24"><rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M9 14h.01M15 14h.01M9.5 17.5h5"/><circle cx="12" cy="3" r="1"/></svg>
        </span>
        <div>
            <h1><?= h($title) ?></h1>
            <p><?= h($company['name']) ?> · cadastre e gerencie a instância do Newbot.</p>
        </div>
    </header>

    <form action="<?= h($action) ?>" method="post">
        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">

        <section class="nb-card">
            <div class="nb-card-head">
                <div><h2>Dados da Instância</h2><p>Identificação e situação operacional do robô.</p></div>
                <span class="nb-tag">Newbot</span>
            </div>
            <div class="nb-grid">
                <label class="nb-field" for="nb-id">
                    <span>ID da instância</span>
                    <input class="nb-input" id="nb-id" value="<?= $isEdit ? h((string) ($instance['id'] ?? '')) : 'Gerado ao salvar' ?>" readonly>
                </label>
                <label class="nb-field" for="instance_name">
                    <span>Nome da Instância<em>*</em></span>
                    <input class="nb-input" id="instance_name" name="instance_name" maxlength="160" value="<?= h($instance['instance_name'] ?? '') ?>" placeholder="Ex.: Atendimento Comercial">
                    <?php if (isset($errors['instance_name'])): ?><small class="nb-error"><?= h($errors['instance_name']) ?></small><?php endif; ?>
                </label>
                <label class="nb-field" for="status">
                    <span>Status<em>*</em></span>
                    <select class="nb-select" id="status" name="status" required>
                        <?php foreach ($statuses as $key => $label): ?>
                            <option value="<?= h($key) ?>" <?= ($instance['status'] ?? 'ativo') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['status'])): ?><small class="nb-error"><?= h($errors['status']) ?></small><?php endif; ?>
                </label>
                <label class="nb-field" for="source_document_id">
                    <span>Documento de Origem</span>
                    <select class="nb-select" id="source_document_id" name="source_document_id">
                        <option value="">Sem vínculo</option>
                        <?php foreach ($documents as $document): ?>
                            <option value="<?= h($document['id']) ?>" <?= (string) ($instance['source_document_id'] ?? '') === (string) $document['id'] ? 'selected' : '' ?>>#<?= h($document['id']) ?> · <?= h($document['original_filename']) ?><?= $document['company_id'] === null ? ' · sem empresa' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['source_document_id'])): ?><small class="nb-error"><?= h($errors['source_document_id']) ?></small><?php endif; ?>
                </label>
                <label class="nb-field nb-wide" for="notes">
                    <span>Observações</span>
                    <textarea class="nb-textarea" id="notes" name="notes" rows="4" maxlength="2000" data-counter="nb-count"><?= h($notes) ?></textarea>
                    <small class="nb-counter"><span id="nb-count"><?= h((string) mb_strlen($notes)) ?></span>/2000</small>
                    <?php if (isset($errors['notes'])): ?><small class="nb-error"><?= h($errors['notes']) ?></small><?php endif; ?>
                </label>
            </div>
        </section>

        <div class="nb-actions">
            <a class="nb-btn nb-btn-quiet" href="<?= h($backUrl) ?>">Voltar</a>
            <button class="nb-btn nb-btn-primary" type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                <?= $isEdit ? 'Salvar alterações' : 'Salvar Instância' ?>
            </button>
        </div>
    </form>
</div>
<script>
(function () {
    var field = document.querySelector('[data-counter]');
    var out = document.getElementById('nb-count');
    if (!field || !out) return;
    var sync = function () { out.textContent = String(field.value.length); };
    field.addEventListener('input', sync);
    sync();
})();
</script>
