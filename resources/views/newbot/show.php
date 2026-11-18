<?php

use NewDoc\Csrf;

$title = 'Newbot · ' . ($instance['instance_name'] ?: 'Instância #' . $instance['id']);
$active = 'newbot';
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção', 'pendente' => 'Pendente'];
?>
<div class="page-heading"><div><a class="back-link" href="/empresas/<?= h($instance['company_id']) ?>/newbot">← Voltar às instâncias</a>
    <span class="eyebrow">NEWBOT · <?= h($instance['company_name']) ?> · ID <?= h($instance['id']) ?></span>
    <h1><?= h($instance['instance_name'] ?: 'Instância #' . $instance['id']) ?></h1><p class="page-subtitle"><?= h($instance['company_name']) ?></p></div>
    <?php if ($canManage): ?><a class="button button-secondary" href="/newbot/<?= h($instance['id']) ?>/editar">Editar instância</a><?php endif; ?></div>
<?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><strong>Não foi possível salvar as alterações:</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="panel"><div class="panel-heading"><div><h2>Dados da instância</h2><p>Configuração geral do atendimento</p></div>
    <span class="status-badge status-<?= h($instance['status']) ?>"><span></span><?= h($statusLabels[$instance['status']] ?? $instance['status']) ?></span></div>
    <dl class="detail-list"><div><dt>Documento de origem</dt><dd><?= $instance['source_document_id'] ? 'Documento #' . h($instance['source_document_id']) : 'Não vinculado' ?></dd></div>
        <?php if ($instance['notes']): ?><div class="detail-wide"><dt>Observações</dt><dd><?= nl2br(h($instance['notes'])) ?></dd></div><?php endif; ?></dl>
    <div class="detail-meta"><span>Criada em <?= h(date('d/m/Y H:i', strtotime($instance['created_at']))) ?></span><span>Atualizada em <?= h(date('d/m/Y H:i', strtotime($instance['updated_at']))) ?></span></div>
</section>
<?php foreach ($sections as $sectionIndex => $section): ?><section class="panel newsic-data-panel"><div class="panel-heading"><div><h2><?= h($section['label']) ?></h2><p><?= h($section['record_count']) ?> registro(s)</p></div>
    <?php if ($canManage): ?><span class="section-number"><?= h(str_pad((string) ($sectionIndex + 2), 2, '0', STR_PAD_LEFT)) ?></span><?php endif; ?></div>
    <?php if ($section['record_count'] === 0): ?><div class="empty-inline">Nenhum registro cadastrado.</div><?php endif; ?>
    <?php if (!$canManage && $section['record_count'] > 0): ?><div class="empty-inline">Dados técnicos restritos a técnicos e administradores.</div><?php endif; ?>
    <?php foreach ($section['records'] as $record): ?><?php
        $recordId = (int) $record['values']['id'];
        $summary = $record['values'][$section['summary_field']] ?? '';
        $summary = $summary === null || $summary === '' ? 'Registro #' . $recordId : (string) $summary;
        $matches = ($formState['section'] ?? null) === $section['type'] && (int) ($formState['record_id'] ?? 0) === $recordId;
        $recordValues = $matches ? array_replace($record['values'], $formState['values'] ?? []) : $record['values'];
    ?><?php if ($canManage): ?><details class="newsic-record"><summary><?= h($summary) ?> · #<?= h($recordId) ?><?php if (!empty($record['values']['status'])): ?> · <?= h($statusLabels[$record['values']['status']] ?? $record['values']['status']) ?><?php endif; ?></summary>
        <form action="/newbot/<?= h($instance['id']) ?>/dados" method="post" class="form-grid newsic-record-form"><input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>"><input type="hidden" name="section" value="<?= h($section['type']) ?>"><input type="hidden" name="record_id" value="<?= h($recordId) ?>">
        <?php $fields = $section['fields']; $values = $recordValues; $secretFields = $record['secret_fields']; $prefix = 'record-' . $section['type'] . '-' . $recordId; require __DIR__ . '/_fields.php'; ?>
        <div class="form-actions form-wide"><button class="button button-secondary" type="submit">Salvar registro</button></div></form>
    </details><?php endif; ?><?php endforeach; ?>
    <?php if ($canManage): ?><?php $matches = ($formState['section'] ?? null) === $section['type'] && ($formState['record_id'] ?? null) === null; ?>
    <details class="newsic-record newsic-record-new" <?= $matches ? 'open' : '' ?>><summary>Adicionar <?= h(strtolower($section['label'])) ?></summary>
        <form action="/newbot/<?= h($instance['id']) ?>/dados" method="post" class="form-grid newsic-record-form"><input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>"><input type="hidden" name="section" value="<?= h($section['type']) ?>"><input type="hidden" name="record_id" value="">
        <?php $fields = $section['fields']; $values = $matches ? ($formState['values'] ?? []) : []; $secretFields = []; $prefix = 'new-' . $section['type']; require __DIR__ . '/_fields.php'; ?>
        <div class="form-actions form-wide"><button class="button button-primary" type="submit">Adicionar registro</button></div></form>
    </details><?php endif; ?>
</section><?php endforeach; ?>
<?php if ($canManage): ?><section class="panel audit-company-panel"><div class="panel-heading"><div><h2>Histórico recente</h2><p>Auditoria da empresa</p></div><a class="text-link" href="/auditoria">Abrir auditoria</a></div>
<?php if ($auditEvents === []): ?><div class="empty-inline">Ainda não há eventos para esta empresa.</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Ação</th><th>Registro</th><th>Campo</th><th>Data e hora</th></tr></thead><tbody>
<?php foreach ($auditEvents as $event): ?><tr><td><?= h(ucfirst($event['action'])) ?></td><td><?= h($event['table_name']) ?> #<?= h($event['record_id'] ?? '—') ?></td><td><?= h($event['field_name'] ?? '—') ?></td><td><?= h(date('d/m/Y H:i', strtotime($event['created_at']))) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section><?php endif; ?>
