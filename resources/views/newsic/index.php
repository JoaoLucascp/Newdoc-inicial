<?php

$title = 'Newsic / IPBX';
$active = 'newsic';
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'];
$typeLabels = ['virtual' => 'Virtual', 'cloud' => 'Cloud', 'fisico' => 'Físico'];
?>
<div class="page-heading">
    <div><span class="eyebrow">SERVIÇO DE TELEFONIA</span><h1>Newsic / IPBX</h1>
        <p class="page-subtitle">Consulte servidores, ramais, troncos, URA, direcionamentos e rede.</p></div>
    <?php if ($canManage): ?><a class="button button-primary" href="/empresas">Selecionar empresa</a><?php endif; ?>
</div>
<section class="panel">
    <form class="filter-bar" action="/newsic" method="get">
        <label class="filter-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <span class="sr-only">Buscar servidor ou empresa</span>
            <input type="search" name="q" value="<?= h($query) ?>" maxlength="160" placeholder="Servidor ou empresa">
        </label>
        <button class="button button-secondary" type="submit">Buscar</button>
        <?php if ($query !== ''): ?><a class="button button-quiet" href="/newsic">Limpar</a><?php endif; ?>
    </form>
    <div class="list-meta"><span><strong><?= h($total) ?></strong> <?= $total === 1 ? 'instância' : 'instâncias' ?></span>
        <span>Credenciais são criptografadas e nunca exibidas nesta tela.</span></div>
    <?php if ($instances === []): ?>
        <div class="empty-state compact-empty"><strong>Nenhum servidor Newsic encontrado</strong>
            <p>Cadastre uma empresa e adicione a instância Newsic correspondente.</p>
            <?php if ($canManage): ?><a class="button button-secondary" href="/empresas">Ver empresas</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Servidor</th><th>Empresa</th><th>Tipo</th><th>Status</th><th>Atualizado em</th><th></th></tr></thead>
            <tbody><?php foreach ($instances as $instance): ?>
                <tr>
                    <td><a class="table-primary-link" href="/newsic/<?= h($instance['id']) ?>"><?= h($instance['server_name']) ?></a></td>
                    <td><a href="/empresas/<?= h($instance['company_id']) ?>"><?= h($instance['company_name']) ?></a></td>
                    <td><?= h($typeLabels[$instance['server_type']] ?? '—') ?></td>
                    <td><span class="status-badge status-<?= h($instance['status']) ?>"><span></span><?= h($statusLabels[$instance['status']] ?? $instance['status']) ?></span></td>
                    <td><?= h(date('d/m/Y', strtotime($instance['updated_at']))) ?></td>
                    <td><a class="row-arrow" href="/newsic/<?= h($instance['id']) ?>" aria-label="Abrir instância">→</a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php if ($pages > 1): ?><nav class="pagination" aria-label="Paginação">
            <span>Página <?= h($page) ?> de <?= h($pages) ?></span><div>
                <?php if ($page > 1): ?><a class="button button-quiet" href="/newsic?<?= h(http_build_query(['q' => $query, 'page' => $page - 1])) ?>">Anterior</a><?php endif; ?>
                <?php if ($page < $pages): ?><a class="button button-quiet" href="/newsic?<?= h(http_build_query(['q' => $query, 'page' => $page + 1])) ?>">Próxima</a><?php endif; ?>
            </div>
        </nav><?php endif; ?>
    <?php endif; ?>
</section>
