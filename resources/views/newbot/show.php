<?php

use NewDoc\Csrf;

$title = 'Newbot';
$active = 'newbot';
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção', 'pendente' => 'Pendente'];
$instanceStatuses = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'];
$instanceId = (int) $instance['id'];
$companyId = (int) $instance['company_id'];
$instanceValues = $instance;
if (($formState['section'] ?? null) === 'instance') {
    $instanceValues = array_replace($instance, $formState['values'] ?? []);
}
$documents = $documents ?? [];
$byType = [];
foreach ($sections as $item) {
    $byType[$item['type']] = $item;
}
$layout = [['channels'], ['administrators', 'supervisors'], ['responsibles', 'integrations'], ['attendants'], ['authentications']];
$tables = [
    'channels' => ['title' => 'Canais de Atendimento', 'singular' => 'canal', 'add' => 'Adicionar Canal', 'columns' => [
        ['name' => 'channel_name', 'label' => 'Canal'],
        ['name' => 'plan', 'label' => 'Plano'],
        ['name' => 'quantity', 'label' => 'Quantidade'],
        ['name' => 'status', 'label' => 'Status', 'kind' => 'status'],
        ['name' => 'activation_date', 'label' => 'Ativação', 'kind' => 'activation'],
    ]],
    'administrators' => ['title' => 'Administradores', 'singular' => 'administrador', 'add' => 'Adicionar', 'columns' => [
        ['name' => 'name', 'label' => 'Nome'],
        ['name' => 'login', 'label' => 'Login'],
        ['name' => 'email', 'label' => 'E-mail'],
        ['name' => 'password', 'label' => 'Senha', 'kind' => 'secret'],
    ]],
    'supervisors' => ['title' => 'Supervisores', 'singular' => 'supervisor', 'add' => 'Adicionar', 'columns' => [
        ['name' => 'name', 'label' => 'Nome'],
        ['name' => 'login', 'label' => 'Login'],
        ['name' => 'email', 'label' => 'E-mail'],
        ['name' => 'password', 'label' => 'Senha', 'kind' => 'secret'],
    ]],
    'responsibles' => ['title' => 'Responsáveis', 'singular' => 'responsável', 'add' => 'Adicionar', 'columns' => [
        ['name' => 'name', 'label' => 'Nome'],
        ['name' => 'contact', 'label' => 'Contato'],
        ['name' => 'email', 'label' => 'E-mail'],
        ['name' => 'department', 'label' => 'Setor'],
    ]],
    'integrations' => ['title' => 'Integrações', 'singular' => 'integração', 'add' => 'Adicionar', 'columns' => [
        ['name' => 'name', 'label' => 'Nome'],
        ['name' => 'username', 'label' => 'Usuário'],
        ['name' => 'password', 'label' => 'Senha', 'kind' => 'secret'],
        ['name' => 'email', 'label' => 'E-mail'],
        ['name' => 'status', 'label' => 'Status', 'kind' => 'status'],
    ]],
    'attendants' => ['title' => 'Atendentes', 'singular' => 'atendente', 'add' => 'Adicionar', 'columns' => [
        ['name' => 'name', 'label' => 'Nome'],
        ['name' => 'username', 'label' => 'Usuário'],
        ['name' => 'email', 'label' => 'E-mail'],
        ['name' => 'department', 'label' => 'Departamento'],
        ['name' => 'attendant_type', 'label' => 'Tipo'],
        ['name' => 'notes', 'label' => 'Observações'],
    ]],
    'authentications' => ['title' => 'Autenticações', 'singular' => 'autenticação', 'add' => 'Adicionar', 'columns' => [
        ['name' => 'platform', 'label' => 'Plataforma', 'kind' => 'platform'],
        ['name' => 'status', 'label' => 'Status', 'kind' => 'status'],
        ['name' => 'notes', 'label' => 'Observações'],
    ]],
];
$svg = static fn (string $path): string => '<svg viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
$iconPlus = $svg('<path d="M12 5v14M5 12h14"/>');
$iconEdit = $svg('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
$iconTrash = $svg('<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>');
$iconSave = $svg('<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>');
$iconDb = $svg('<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>');
$iconExternal = $svg('<path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>');
$iconHeadset = $svg('<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1"/><rect x="17" y="14" width="4" height="6" rx="1"/>');
$iconUsers = $svg('<circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14a5 5 0 0 1 4 5v1"/>');
$iconUser = $svg('<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/>');
$iconLink = $svg('<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>');
$iconKey = $svg('<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>');
$titleIcons = [
    'channels' => $iconHeadset, 'administrators' => $iconUsers, 'supervisors' => $iconUsers,
    'responsibles' => $iconUser, 'integrations' => $iconLink, 'attendants' => $iconHeadset, 'authentications' => $iconKey,
];
$cell = static function (array $record, array $column) use ($statusLabels): string {
    $values = $record['values'];
    $kind = $column['kind'] ?? 'text';
    $name = $column['name'];
    if ($kind === 'secret') {
        return !empty($record['secret_fields'][$name]) ? '••••••••' : '—';
    }
    if ($kind === 'status') {
        $status = (string) ($values['status'] ?? '');
        if ($status === '') {
            return '—';
        }

        return '<span class="nbi-status nbi-status-' . h($status) . '"><i></i>' . h($statusLabels[$status] ?? $status) . '</span>';
    }
    if ($kind === 'activation') {
        $date = (string) ($values['activation_date'] ?? '');
        if ($date === '') {
            return '—';
        }
        $time = (string) ($values['activation_time'] ?? '');
        $stamp = strtotime($date);
        $text = $stamp === false ? $date : date('d/m/Y', $stamp);

        return h($time !== '' ? $text . ' ' . substr($time, 0, 5) : $text);
    }
    $value = $values[$name] ?? '';
    if ($value === null || $value === '') {
        return '—';
    }
    if ($kind === 'platform') {
        return h(ucfirst((string) $value));
    }

    return h((string) $value);
};
$accessLink = (string) ($instanceValues['access_link'] ?? '');
$accessLinkValid = $accessLink !== '' && preg_match('#^https?://#i', $accessLink) === 1;
?>
<link rel="stylesheet" href="/assets/newbot-form.css?v=2">
<link rel="stylesheet" href="/assets/newbot-instance.css?v=2">
<script src="/assets/newbot-form.js?v=2" defer></script>
<script src="/assets/newbot-instance.js?v=1" defer></script>
<div class="nb-page">
<nav class="nb-crumbs" aria-label="Trilha de navegação"><a href="/">Início</a><span aria-hidden="true">›</span><a href="/newbot">Newbot</a><span aria-hidden="true">›</span><a href="/empresas/<?= $companyId ?>/newbot">Instâncias</a><span aria-hidden="true">›</span><span aria-current="page"><?= h($instance['instance_name'] ?: 'Instância #' . $instanceId) ?></span></nav>
<header class="nb-head"><div><h1><?= h($instance['company_name']) ?></h1><p>Cadastre e gerencie a instância do Newbot.</p></div></header>

<?php if ($errors !== []): ?>
    <div class="alert alert-error" role="alert">
        <strong>Não foi possível salvar as alterações:</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($canManage): ?>
<form id="nbi-instance-form" action="/newbot/<?= $instanceId ?>" method="post">
<input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
<section class="nb-card" aria-labelledby="nbi-data-title">
<div class="nb-card-head"><div class="nbi-head-main"><span class="nbi-title-icon" aria-hidden="true"><?= $iconDb ?></span><div><h2 id="nbi-data-title">Dados da Instância</h2><p>Identificação e situação operacional do robô.</p></div></div></div>
<div class="nb-grid">
<label class="nb-field" for="nbi-internal-id"><span>ID interno do Newdoc</span><input class="nb-input" id="nbi-internal-id" value="#<?= $instanceId ?>" readonly></label>
<label class="nb-field" for="instance_name"><span>Nome da Instância</span><input class="nb-input" id="instance_name" name="instance_name" maxlength="160" value="<?= h($instanceValues['instance_name'] ?? '') ?>" placeholder="Digite o nome da instância..."></label>
<label class="nb-field" for="status"><span>Status <em>*</em></span><select class="nb-select" id="status" name="status" required><?php foreach ($instanceStatuses as $key => $label): ?><option value="<?= h($key) ?>" <?= ($instanceValues['status'] ?? 'ativo') === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label class="nb-field" for="source_document_id"><span>Documento de Origem</span><select class="nb-select" id="source_document_id" name="source_document_id"><option value="">Sem vínculo</option><?php foreach ($documents as $document): ?><option value="<?= h($document['id']) ?>" <?= (string) ($instanceValues['source_document_id'] ?? '') === (string) $document['id'] ? 'selected' : '' ?>>#<?= h($document['id']) ?> · <?= h($document['original_filename']) ?><?= $document['company_id'] === null ? ' · sem empresa' : '' ?></option><?php endforeach; ?></select></label>
<label class="nb-field" for="instance_code"><span>ID da Instância</span><input class="nb-input" id="instance_code" name="instance_code" maxlength="160" value="<?= h($instanceValues['instance_code'] ?? '') ?>" placeholder="Ex.: newtel-comercial"></label>
<div class="nb-field"><label for="access_link"><span>Link de acesso</span></label><input class="nb-input" id="access_link" name="access_link" type="url" maxlength="500" value="<?= h($accessLink) ?>" placeholder="https://"><?php if ($accessLinkValid): ?><a class="nbi-open-link" href="<?= h($accessLink) ?>" target="_blank" rel="noopener noreferrer" aria-label="Abrir link de acesso" title="Abrir link de acesso"><?= $iconExternal ?> Abrir link</a><?php endif; ?></div>
<label class="nb-field nb-wide" for="notes"><span>Observações</span><textarea class="nb-textarea" id="notes" name="notes" rows="3" maxlength="2000" data-nb-counter="nb-count" aria-describedby="nb-count"><?= h($instanceValues['notes'] ?? '') ?></textarea><small class="nb-counter" id="nb-count"><?= h((string) mb_strlen((string) ($instanceValues['notes'] ?? ''))) ?>/2000</small></label>
</div>
<p class="nbi-meta">Criada em <?= h(date('d/m/Y H:i', strtotime($instance['created_at']))) ?> · Atualizada em <?= h(date('d/m/Y H:i', strtotime($instance['updated_at']))) ?></p>
</section>
</form>
<?php else: ?>
<section class="nb-card">
<div class="nb-card-head"><div><h2>Dados da Instância</h2><p>Identificação e situação operacional do robô.</p></div><span class="nb-tag"><?= h($statusLabels[$instance['status']] ?? $instance['status']) ?></span></div>
<dl class="nbi-dl">
<div><dt>ID da instância</dt><dd><?= $instance['instance_code'] ? h($instance['instance_code']) : 'Não informado' ?></dd></div>
<div><dt>Link de acesso</dt><dd><?php if ($accessLinkValid): ?><a class="nbi-open-link" href="<?= h($accessLink) ?>" target="_blank" rel="noopener noreferrer"><?= h($accessLink) ?></a><?php else: ?>Não informado<?php endif; ?></dd></div>
<div><dt>Documento de origem</dt><dd><?= $instance['source_document_id'] ? 'Documento #' . h($instance['source_document_id']) : 'Não vinculado' ?></dd></div>
<?php if ($instance['notes']): ?><div><dt>Observações</dt><dd><?= nl2br(h($instance['notes'])) ?></dd></div><?php endif; ?>
</dl>
</section>
<?php endif; ?>

<?php foreach ($layout as $group): ?>
<div class="nbi-row nbi-row-<?= count($group) ?>">
<?php foreach ($group as $type): ?>
    <?php
    if (!isset($byType[$type])) {
        continue;
    }
    $section = $byType[$type];
    $spec = $tables[$type];
    ?>
    <section class="nbi-card" aria-labelledby="nbi-title-<?= h($type) ?>">
        <div class="nbi-card-head">
            <h2 id="nbi-title-<?= h($type) ?>"><span class="nbi-title-icon" aria-hidden="true"><?= $titleIcons[$type] ?? '' ?></span><?= h($spec['title']) ?></h2>
            <?php if ($canManage): ?>
                <button class="nbi-add" type="button" data-dialog-open="nbi-new-<?= h($type) ?>"><?= $iconPlus ?><?= h($spec['add']) ?></button>
            <?php endif; ?>
        </div>
        <?php if (!$canManage): ?>
            <div class="nbi-note"><?= (int) $section['record_count'] > 0 ? 'Dados técnicos restritos a técnicos e administradores.' : 'Nenhum registro cadastrado.' ?></div>
        <?php else: ?>
            <div class="nbi-table-wrap">
                <table class="nbi-table">
                    <thead>
                        <tr>
                            <?php foreach ($spec['columns'] as $column): ?><th><?= h($column['label']) ?></th><?php endforeach; ?>
                            <th class="nbi-actions-col">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($section['records'] === []): ?>
                            <tr><td class="nbi-note" colspan="<?= count($spec['columns']) + 1 ?>">Nenhum registro cadastrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($section['records'] as $record): ?>
                            <?php $recordId = (int) $record['values']['id']; ?>
                            <tr>
                                <?php foreach ($spec['columns'] as $column): ?><td><?= $cell($record, $column) ?></td><?php endforeach; ?>
                                <td class="nbi-actions-col">
                                    <div class="nbi-actions-cell">
                                        <button class="nbi-icon nbi-icon-edit" type="button" data-dialog-open="nbi-edit-<?= h($type) ?>-<?= h($recordId) ?>" aria-label="Editar registro" title="Editar registro"><?= $iconEdit ?></button>
                                        <form class="nbi-inline" action="/newbot/<?= $instanceId ?>/dados/excluir" method="post" data-confirm="Excluir este registro permanentemente? Esta ação não pode ser desfeita.">
                                            <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                                            <input type="hidden" name="section" value="<?= h($type) ?>">
                                            <input type="hidden" name="record_id" value="<?= h($recordId) ?>">
                                            <button class="nbi-icon nbi-icon-danger" type="submit" aria-label="Excluir registro" title="Excluir registro"><?= $iconTrash ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if ($canManage): ?>
    <section class="panel audit-company-panel">
        <div class="panel-heading">
            <div>
                <h2>Histórico recente</h2>
                <p>Auditoria da empresa</p>
            </div>
            <a class="text-link" href="/auditoria">Abrir auditoria</a>
        </div>
        <?php if ($auditEvents === []): ?>
            <div class="empty-inline">Ainda não há eventos para esta empresa.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ação</th>
                            <th>Registro</th>
                            <th>Campo</th>
                            <th>Data e hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditEvents as $event): ?>
                            <tr>
                                <td><?= h(ucfirst($event['action'])) ?></td>
                                <td><?= h($event['table_name']) ?> #<?= h($event['record_id'] ?? '—') ?></td>
                                <td><?= h($event['field_name'] ?? '—') ?></td>
                                <td><?= h(date('d/m/Y H:i', strtotime($event['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="nb-actions nbi-sticky">
    <a class="nb-btn nb-btn-quiet" href="/empresas/<?= $companyId ?>/newbot">Voltar</a>
    <?php if ($canManage): ?>
        <button class="nb-btn nb-btn-primary" type="submit" form="nbi-instance-form"><?= $iconSave ?>Salvar Instância</button>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
    <?php foreach ($byType as $type => $section): ?>
        <?php $spec = $tables[$type]; ?>
        <?php foreach ($section['records'] as $record): ?>
            <?php
            $recordId = (int) $record['values']['id'];
            $matches = ($formState['section'] ?? null) === $type && (int) ($formState['record_id'] ?? 0) === $recordId;
            $recordValues = $matches ? array_replace($record['values'], $formState['values'] ?? []) : $record['values'];
            ?>
            <dialog class="nbi-dialog" id="nbi-edit-<?= h($type) ?>-<?= h($recordId) ?>" aria-labelledby="nbi-edit-title-<?= h($type) ?>-<?= h($recordId) ?>" <?= $matches ? 'data-open' : '' ?>>
                <form class="nbi-dialog-form" action="/newbot/<?= $instanceId ?>/dados" method="post">
                    <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                    <input type="hidden" name="section" value="<?= h($type) ?>">
                    <input type="hidden" name="record_id" value="<?= h($recordId) ?>">
                    <div class="nbi-dialog-head"><h2 id="nbi-edit-title-<?= h($type) ?>-<?= h($recordId) ?>">Editar <?= h($spec['singular']) ?></h2></div>
                    <?php if ($matches && $errors !== []): ?>
                        <ul class="nbi-errors"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                    <div class="nbi-dialog-body">
                        <?php
                        $fields = $section['fields'];
                        $values = $recordValues;
                        $secretFields = $record['secret_fields'];
                        $prefix = 'record-' . $type . '-' . $recordId;
                        require __DIR__ . '/_fields.php';
                        ?>
                    </div>
                    <div class="nbi-dialog-foot">
                        <button class="nb-btn nb-btn-quiet" type="button" data-dialog-close>Cancelar</button>
                        <button class="nb-btn nb-btn-primary" type="submit">Salvar</button>
                    </div>
                </form>
            </dialog>
        <?php endforeach; ?>
        <?php $newMatches = ($formState['section'] ?? null) === $type && ($formState['record_id'] ?? null) === null; ?>
        <dialog class="nbi-dialog" id="nbi-new-<?= h($type) ?>" aria-labelledby="nbi-new-title-<?= h($type) ?>" <?= $newMatches ? 'data-open' : '' ?>>
            <form class="nbi-dialog-form" action="/newbot/<?= $instanceId ?>/dados" method="post">
                <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                <input type="hidden" name="section" value="<?= h($type) ?>">
                <input type="hidden" name="record_id" value="">
                <div class="nbi-dialog-head"><h2 id="nbi-new-title-<?= h($type) ?>">Adicionar <?= h($spec['singular']) ?></h2></div>
                <?php if ($newMatches && $errors !== []): ?>
                    <ul class="nbi-errors"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <div class="nbi-dialog-body">
                    <?php
                    $fields = $section['fields'];
                    $values = $newMatches ? ($formState['values'] ?? []) : [];
                    $secretFields = [];
                    $prefix = 'new-' . $type;
                    require __DIR__ . '/_fields.php';
                    ?>
                </div>
                <div class="nbi-dialog-foot">
                    <button class="nb-btn nb-btn-quiet" type="button" data-dialog-close>Cancelar</button>
                    <button class="nb-btn nb-btn-primary" type="submit">Adicionar</button>
                </div>
            </form>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>
</div>