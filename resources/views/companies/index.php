<?php

$title = 'Empresas';
$active = 'companies';
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'inexistente' => 'Sem contrato'];
$queryParams = ['q' => $query, 'status' => $status];
?>
<div class="page-heading">
    <div><span class="eyebrow">CADASTRO E CONSULTA</span><h1>Empresas</h1>
        <p class="page-subtitle">Pesquise empresas e consulte sua situação cadastral.</p></div>
    <?php if ($canManage): ?><a class="button button-primary" href="/empresas/nova"><span class="button-plus">+</span> Nova empresa</a><?php endif; ?>
</div>
<section class="panel">
    <form class="filter-bar" action="/empresas" method="get">
        <label class="filter-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <span class="sr-only">Buscar</span>
            <input type="search" name="q" value="<?= h($query) ?>" maxlength="160"
                   placeholder="Nome, CNPJ, e-mail ou responsável">
        </label>
        <label class="sr-only" for="contract-status">Filtrar por contrato</label>
        <select class="form-select filter-select" id="contract-status" name="status">
            <option value="">Todas as situações</option>
            <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-secondary" type="submit">Filtrar</button>
        <?php if ($query !== '' || $status !== ''): ?><a class="button button-quiet" href="/empresas">Limpar</a><?php endif; ?>
    </form>
    <div class="list-meta"><span><strong><?= h($total) ?></strong> <?= $total === 1 ? 'empresa encontrada' : 'empresas encontradas' ?></span>
        <span>Nome da empresa não é identificador único.</span></div>
    <?php if ($companies === []): ?>
        <div class="empty-state compact-empty"><span class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></span>
            <strong>Nenhuma empresa encontrada</strong><p>Ajuste os filtros ou cadastre uma nova empresa.</p>
            <?php if ($canManage): ?><a class="button button-secondary" href="/empresas/nova">Nova empresa</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Empresa</th><th>CNPJ</th><th>Responsável</th><th>Contrato</th><th>Atualização</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($companies as $company): ?>
                    <tr>
                        <td><a class="table-primary-link" href="/empresas/<?= h($company['id']) ?>"><?= h($company['name']) ?></a>
                            <small><?= h($company['trade_name'] ?? '') ?></small></td>
                        <td><?= h($company['cnpj'] ?: '—') ?></td>
                        <td><?= h($company['responsible_name'] ?: '—') ?></td>
                        <td><span class="status-badge status-<?= h($company['contract_status']) ?>"><span></span><?= h($statusLabels[$company['contract_status']] ?? 'Não informado') ?></span></td>
                        <td><?= h(date('d/m/Y', strtotime($company['updated_at']))) ?></td>
                        <td><a class="row-arrow" href="/empresas/<?= h($company['id']) ?>" aria-label="Abrir empresa">→</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Paginação">
                <span>Página <?= h($page) ?> de <?= h($pages) ?></span>
                <div>
                    <?php if ($page > 1): ?><a class="button button-quiet" href="/empresas?<?= h(http_build_query($queryParams + ['page' => $page - 1])) ?>">Anterior</a><?php endif; ?>
                    <?php if ($page < $pages): ?><a class="button button-quiet" href="/empresas?<?= h(http_build_query($queryParams + ['page' => $page + 1])) ?>">Próxima</a><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
