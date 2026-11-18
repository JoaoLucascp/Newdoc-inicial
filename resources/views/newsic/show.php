<?php

use NewDoc\Csrf;

$title = 'Newsic · ' . $instance['server_name'];
$active = 'newsic';
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'];
$typeLabels = ['virtual' => 'Virtual', 'cloud' => 'Cloud', 'fisico' => 'Físico'];
?>
<div class="page-heading">
    <div><a class="back-link" href="/empresas/<?= h($instance['company_id']) ?>/newsic">← Voltar às instâncias</a>
        <span class="eyebrow">NEWSIC / IPBX · <?= h($instance['company_name']) ?> · ID <?= h($instance['id']) ?></span>
        <h1><?= h($instance['server_name']) ?></h1>
        <p class="page-subtitle"><?= h($typeLabels[$instance['server_type']] ?? 'Tipo não informado') ?> · <?= h($instance['company_name']) ?></p>
    </div>
    <?php if ($canManage): ?><a class="button button-secondary" href="/newsic/<?= h($instance['id']) ?>/editar">Editar servidor</a><?php endif; ?>
</div>
<?php if ($errors !== []): ?><div class="alert alert-error" role="alert">
    <strong>Não foi possível salvar as alterações:</strong>
    <ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
</div><?php endif; ?>
<section class="panel">
    <div class="panel-heading"><div><h2>Dados do servidor</h2><p>Informações de acesso e configuração</p></div>
        <span class="status-badge status-<?= h($instance['status']) ?>"><span></span><?= h($statusLabels[$instance['status']] ?? $instance['status']) ?></span>
    </div>
    <dl class="detail-list">
        <div><dt>Versão</dt><dd><?= h($instance['software_version'] ?: '—') ?></dd></div>
        <div><dt>Documento de origem</dt><dd><?= $instance['source_document_id'] ? 'Documento #' . h($instance['source_document_id']) : 'Não vinculado' ?></dd></div>
        <?php if ($canManage): ?>
            <div><dt>IP interno</dt><dd><?= h($instance['internal_ip'] ?: 'Não informado') ?></dd></div>
            <div><dt>IP externo</dt><dd><?= h($instance['external_ip'] ?: 'Não informado') ?></dd></div>
            <div><dt>Porta web</dt><dd><?= h($instance['web_port'] ?: '—') ?></dd></div>
            <div><dt>Usuário web</dt><dd><?= h($instance['web_username'] ?: '—') ?></dd></div>
            <div><dt>Senha web</dt><dd><?= !empty($instance['has_web_password']) ? 'Configurada · criptografada' : 'Não configurada' ?></dd></div>
            <div><dt>Porta SSH</dt><dd><?= h($instance['ssh_port'] ?: '—') ?></dd></div>
            <div><dt>Usuário SSH</dt><dd><?= h($instance['ssh_username'] ?: '—') ?></dd></div>
            <div><dt>Senha SSH</dt><dd><?= !empty($instance['has_ssh_password']) ? 'Configurada · criptografada' : 'Não configurada' ?></dd></div>
            <div class="detail-wide"><dt>Link FreeMyIP</dt><dd><?= h($instance['freemyip_link'] ?: '—') ?></dd></div>
            <div class="detail-wide"><dt>Documentação</dt><dd><?= h($instance['documentation_path'] ?: '—') ?></dd></div>
            <?php if ($instance['notes']): ?><div class="detail-wide"><dt>Observações</dt><dd><?= nl2br(h($instance['notes'])) ?></dd></div><?php endif; ?>
        <?php else: ?>
            <div class="detail-wide"><dt>Informações de acesso</dt><dd>Restritas a técnicos e administradores.</dd></div>
        <?php endif; ?>
    </dl>
    <div class="detail-meta"><span>Criada em <?= h(date('d/m/Y H:i', strtotime($instance['created_at']))) ?></span>
        <span>Atualizada em <?= h(date('d/m/Y H:i', strtotime($instance['updated_at']))) ?></span></div>
</section>
<div class="notice notice-info"><span>
    <strong>Credenciais protegidas:</strong> valores de senha não são exibidos. Técnicos autorizados podem substituir ou remover uma credencial sem revelar o valor atual.
</span></div>
<?php foreach ($sections as $sectionIndex => $section): ?>
    <section class="panel newsic-data-panel">
        <div class="panel-heading">
            <div><h2><?= h($section['label']) ?></h2><p><?= h($section['record_count']) ?> registro(s)</p></div>
            <?php if ($canManage): ?><span class="section-number"><?= h(str_pad((string) ($sectionIndex + 2), 2, '0', STR_PAD_LEFT)) ?></span><?php endif; ?>
        </div>
        <?php if ($section['record_count'] === 0): ?><div class="empty-inline">Nenhum registro cadastrado.</div><?php endif; ?>
        <?php if (!$canManage && $section['record_count'] > 0): ?>
            <div class="empty-inline">Dados técnicos restritos a técnicos e administradores.</div>
        <?php endif; ?>
        <?php foreach ($section['records'] as $record): ?>
            <?php
            $recordId = (int) $record['values']['id'];
            $summary = $record['values'][$section['summary_field']] ?? '';
            $summary = $summary === '' ? 'Registro #' . $recordId : (string) $summary;
            $formStateMatches = ($formState['section'] ?? null) === $section['type']
                && (int) ($formState['record_id'] ?? 0) === $recordId;
            $recordValues = $formStateMatches
                ? array_replace($record['values'], $formState['values'] ?? [])
                : $record['values'];
            ?>
            <?php if ($canManage): ?><details class="newsic-record">
                <summary><?= h($summary) ?> · #<?= h($recordId) ?><?php if (!empty($record['values']['status'])): ?> · <?= h($statusLabels[$record['values']['status']] ?? $record['values']['status']) ?><?php endif; ?></summary>
                    <form action="/newsic/<?= h($instance['id']) ?>/dados" method="post" class="form-grid newsic-record-form">
                        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                        <input type="hidden" name="section" value="<?= h($section['type']) ?>">
                        <input type="hidden" name="record_id" value="<?= h($recordId) ?>">
                        <?php
                        $fields = $section['fields'];
                        $values = $recordValues;
                        $secretFields = $record['secret_fields'];
                        $prefix = 'record-' . $section['type'] . '-' . $recordId;
                        require __DIR__ . '/_fields.php';
                        ?>
                        <div class="form-actions form-wide"><button class="button button-secondary" type="submit">Salvar registro</button></div>
                    </form>
            </details><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($canManage): ?>
            <?php
            $newFormStateMatches = ($formState['section'] ?? null) === $section['type']
                && ($formState['record_id'] ?? null) === null;
            $newValues = $newFormStateMatches ? ($formState['values'] ?? []) : [];
            ?>
            <details class="newsic-record newsic-record-new" <?= $newFormStateMatches ? 'open' : '' ?>>
                <summary>Adicionar <?= h(strtolower($section['label'])) ?></summary>
                <form action="/newsic/<?= h($instance['id']) ?>/dados" method="post" class="form-grid newsic-record-form">
                    <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                    <input type="hidden" name="section" value="<?= h($section['type']) ?>">
                    <input type="hidden" name="record_id" value="">
                    <?php
                    $fields = $section['fields'];
                    $values = $newValues;
                    $secretFields = [];
                    $prefix = 'new-' . $section['type'];
                    require __DIR__ . '/_fields.php';
                    ?>
                    <div class="form-actions form-wide"><button class="button button-primary" type="submit">Adicionar registro</button></div>
                </form>
            </details>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
<?php if ($canManage): ?><section class="panel audit-company-panel">
    <div class="panel-heading"><div><h2>Histórico recente</h2><p>Auditoria da empresa</p></div><a class="text-link" href="/auditoria">Abrir auditoria</a></div>
    <?php if ($auditEvents === []): ?><div class="empty-inline">Ainda não há eventos para esta empresa.</div>
    <?php else: ?><div class="table-wrap"><table class="data-table">
        <thead><tr><th>Ação</th><th>Registro</th><th>Campo</th><th>Data e hora</th></tr></thead>
        <tbody><?php foreach ($auditEvents as $event): ?><tr>
            <td><?= h(ucfirst($event['action'])) ?></td><td><?= h($event['table_name']) ?> #<?= h($event['record_id'] ?? '—') ?></td>
            <td><?= h($event['field_name'] ?? '—') ?></td><td><?= h(date('d/m/Y H:i', strtotime($event['created_at']))) ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>
<?php endif; ?>
