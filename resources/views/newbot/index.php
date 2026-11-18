<?php

$title = 'Newbot';
$active = 'newbot';
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'];
?>
<div class="page-heading">
    <div><span class="eyebrow">ATENDIMENTO OMNICHANNEL</span><h1>Newbot</h1>
        <p class="page-subtitle">Consulte instâncias, canais, integrações e equipes de atendimento.</p></div>
    <?php if ($canManage): ?><a class="button button-primary" href="/empresas">Selecionar empresa</a><?php endif; ?>
</div>
<section class="panel">
    <form class="filter-bar" action="/newbot" method="get">
        <label class="filter-search"><span class="sr-only">Buscar instância ou empresa</span>
            <input type="search" name="q" value="<?= h($query) ?>" maxlength="160" placeholder="Instância ou empresa"></label>
        <button class="button button-secondary" type="submit">Buscar</button>
        <?php if ($query !== ''): ?><a class="button button-quiet" href="/newbot">Limpar</a><?php endif; ?>
    </form>
    <div class="list-meta"><span><strong><?= h($total) ?></strong> <?= $total === 1 ? 'instância' : 'instâncias' ?></span>
        <span>Credenciais são criptografadas e nunca exibidas.</span></div>
    <?php if ($instances === []): ?>
        <div class="empty-state compact-empty"><strong>Nenhuma instância Newbot encontrada</strong>
            <p>Cadastre uma empresa e adicione a instância Newbot correspondente.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table"><thead>
            <tr><th>Instância</th><th>Empresa</th><th>Status</th><th>Atualizado em</th><th></th></tr>
        </thead><tbody><?php foreach ($instances as $instance): ?><tr>
            <td><a class="table-primary-link" href="/newbot/<?= h($instance['id']) ?>"><?= h($instance['instance_name'] ?: 'Instância #' . $instance['id']) ?></a></td>
            <td><a href="/empresas/<?= h($instance['company_id']) ?>"><?= h($instance['company_name']) ?></a></td>
            <td><span class="status-badge status-<?= h($instance['status']) ?>"><span></span><?= h($statusLabels[$instance['status']] ?? $instance['status']) ?></span></td>
            <td><?= h(date('d/m/Y', strtotime($instance['updated_at']))) ?></td>
            <td><a class="row-arrow" href="/newbot/<?= h($instance['id']) ?>" aria-label="Abrir instância">→</a></td>
        </tr><?php endforeach; ?></tbody></table></div>
        <?php if ($pages > 1): ?><nav class="pagination" aria-label="Paginação"><span>Página <?= h($page) ?> de <?= h($pages) ?></span><div>
            <?php if ($page > 1): ?><a class="button button-quiet" href="/newbot?<?= h(http_build_query(['q' => $query, 'page' => $page - 1])) ?>">Anterior</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="button button-quiet" href="/newbot?<?= h(http_build_query(['q' => $query, 'page' => $page + 1])) ?>">Próxima</a><?php endif; ?>
        </div></nav><?php endif; ?>
    <?php endif; ?>
</section>
