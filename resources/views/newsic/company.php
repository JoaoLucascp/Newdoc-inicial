<?php

$title = 'Newsic · ' . $company['name'];
$active = 'companies';
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'];
$typeLabels = ['virtual' => 'Virtual', 'cloud' => 'Cloud', 'fisico' => 'Físico'];
?>
<div class="page-heading">
    <div><a class="back-link" href="/empresas/<?= h($company['id']) ?>">← Voltar à empresa</a>
        <span class="eyebrow">NEWSIC / IPBX · EMPRESA #<?= h($company['id']) ?></span>
        <h1><?= h($company['name']) ?></h1><p class="page-subtitle">Instâncias atuais e registros históricos.</p></div>
    <?php if ($canManage): ?><a class="button button-primary" href="/empresas/<?= h($company['id']) ?>/newsic/nova">Adicionar instância</a><?php endif; ?>
</div>
<section class="panel">
    <div class="panel-heading"><div><h2>Instâncias Newsic</h2><p>Uma instância ativa por empresa; registros inativos podem ser mantidos como histórico.</p></div></div>
    <?php if ($instances === []): ?>
        <div class="empty-state compact-empty"><strong>Nenhuma instância cadastrada</strong>
            <p>As credenciais e configurações técnicas só serão gravadas após salvar o formulário.</p>
            <?php if ($canManage): ?><a class="button button-secondary" href="/empresas/<?= h($company['id']) ?>/newsic/nova">Cadastrar Newsic</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Servidor</th><th>Tipo</th><th>Versão</th><th>Status</th><th>Atualizado em</th><th></th></tr></thead>
            <tbody><?php foreach ($instances as $instance): ?>
                <tr>
                    <td><a class="table-primary-link" href="/newsic/<?= h($instance['id']) ?>"><?= h($instance['server_name']) ?></a></td>
                    <td><?= h($typeLabels[$instance['server_type']] ?? '—') ?></td>
                    <td><?= h($instance['software_version'] ?: '—') ?></td>
                    <td><span class="status-badge status-<?= h($instance['status']) ?>"><span></span><?= h($statusLabels[$instance['status']] ?? $instance['status']) ?></span></td>
                    <td><?= h(date('d/m/Y', strtotime($instance['updated_at']))) ?></td>
                    <td><a class="row-arrow" href="/newsic/<?= h($instance['id']) ?>" aria-label="Abrir instância">→</a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
