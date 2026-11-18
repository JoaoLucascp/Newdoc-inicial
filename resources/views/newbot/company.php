<?php

$title = 'Newbot · ' . $company['name'];
$active = 'companies';
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'];
?>
<div class="page-heading">
    <div><a class="back-link" href="/empresas/<?= h($company['id']) ?>">← Voltar à empresa</a>
        <span class="eyebrow">NEWBOT · EMPRESA #<?= h($company['id']) ?></span><h1><?= h($company['name']) ?></h1>
        <p class="page-subtitle">Instâncias atuais e registros históricos.</p></div>
    <?php if ($canManage): ?><a class="button button-primary" href="/empresas/<?= h($company['id']) ?>/newbot/nova">Adicionar instância</a><?php endif; ?>
</div>
<section class="panel"><div class="panel-heading"><div><h2>Instâncias Newbot</h2><p>Uma instância ativa por empresa; registros inativos podem ser mantidos como histórico.</p></div></div>
<?php if ($instances === []): ?><div class="empty-state compact-empty"><strong>Nenhuma instância cadastrada</strong>
    <p>Cadastre a configuração do atendimento omnichannel desta empresa.</p>
    <?php if ($canManage): ?><a class="button button-secondary" href="/empresas/<?= h($company['id']) ?>/newbot/nova">Cadastrar Newbot</a><?php endif; ?>
</div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Instância</th><th>Status</th><th>Atualizado em</th><th></th></tr></thead>
<tbody><?php foreach ($instances as $instance): ?><tr><td><a class="table-primary-link" href="/newbot/<?= h($instance['id']) ?>"><?= h($instance['instance_name'] ?: 'Instância #' . $instance['id']) ?></a></td>
<td><span class="status-badge status-<?= h($instance['status']) ?>"><span></span><?= h($statusLabels[$instance['status']] ?? $instance['status']) ?></span></td>
<td><?= h(date('d/m/Y', strtotime($instance['updated_at']))) ?></td><td><a class="row-arrow" href="/newbot/<?= h($instance['id']) ?>" aria-label="Abrir instância">→</a></td></tr><?php endforeach; ?></tbody>
</table></div><?php endif; ?></section>
