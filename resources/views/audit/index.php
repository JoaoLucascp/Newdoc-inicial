<?php

$title = 'Auditoria';
$active = 'audit';
?>
<div class="page-heading">
    <div><span class="eyebrow">RASTREABILIDADE</span><h1>Auditoria</h1>
        <p class="page-subtitle">Histórico de ações. Valores anteriores e novos não são armazenados.</p></div>
</div>
<section class="panel">
    <form class="filter-bar" action="/auditoria" method="get">
        <label class="sr-only" for="audit-action">Ação</label>
        <select class="form-select filter-select" id="audit-action" name="action"><option value="">Todas as ações</option>
            <?php foreach ($actions as $item): ?><option value="<?= h($item) ?>" <?= $action === $item ? 'selected' : '' ?>><?= h(ucfirst($item)) ?></option><?php endforeach; ?>
        </select>
        <label class="filter-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <span class="sr-only">Filtrar por tabela</span><input type="search" name="table" value="<?= h($table) ?>" placeholder="Tabela" maxlength="80">
        </label>
        <button class="button button-secondary" type="submit">Filtrar</button>
    </form>
    <div class="list-meta"><span><strong><?= h($total) ?></strong> eventos registrados</span><span>Senhas, tokens e credenciais não aparecem nesta tela.</span></div>
    <?php if ($events === []): ?><div class="empty-state compact-empty"><strong>Nenhum evento encontrado</strong><p>Os eventos de autenticação, cadastro e alteração aparecerão aqui.</p></div>
    <?php else: ?><div class="table-wrap"><table class="data-table">
        <thead><tr><th>Data e hora</th><th>Usuário</th><th>Ação</th><th>Registro</th><th>Campo</th><th>Empresa</th><th>Origem</th></tr></thead>
        <tbody><?php foreach ($events as $event): ?><tr>
            <td><?= h(date('d/m/Y H:i:s', strtotime($event['created_at']))) ?></td>
            <td><?= h($event['user_name'] ?? 'Sistema') ?></td>
            <td><span class="action-tag"><?= h(ucfirst($event['action'])) ?></span></td>
            <td><?= h($event['table_name']) ?><?= $event['record_id'] ? ' #' . h($event['record_id']) : '' ?></td>
            <td><?= h($event['sensitive_field'] ? 'Campo sensível alterado' : ($event['field_name'] ?? '—')) ?></td>
            <td><?php if ($event['company_id']): ?><a class="text-link" href="/empresas/<?= h($event['company_id']) ?>"><?= h($event['company_name']) ?> · #<?= h($event['company_id']) ?></a><?php else: ?>—<?php endif; ?></td>
            <td><?= h($event['ip_address'] ?? '—') ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php if ($pages > 1): ?><nav class="pagination" aria-label="Paginação">
        <span>Página <?= h($page) ?> de <?= h($pages) ?></span><div>
            <?php $pageParams = ['action' => $action, 'table' => $table]; ?>
            <?php if ($page > 1): ?><a class="button button-quiet" href="/auditoria?<?= h(http_build_query($pageParams + ['page' => $page - 1])) ?>">Anterior</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="button button-quiet" href="/auditoria?<?= h(http_build_query($pageParams + ['page' => $page + 1])) ?>">Próxima</a><?php endif; ?>
        </div>
    </nav><?php endif; ?>
    <?php endif; ?>
</section>
