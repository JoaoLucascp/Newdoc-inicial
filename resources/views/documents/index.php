<?php

use NewDoc\Csrf;

$title = 'Documentos';
$active = 'documents';
$typeLabels = ['newsic' => 'Newsic', 'newbot' => 'Newbot', 'newvoz' => 'Newvoz', 'contatos' => 'Contatos', 'outro' => 'Outro'];
$statusLabels = ['pendente' => 'Pendente', 'em_analise' => 'Em análise', 'validado' => 'Validado', 'historico' => 'Histórico', 'descartado' => 'Descartado'];
?>
<div class="page-heading">
    <div><span class="eyebrow">ACERVO DE ORIGINAIS</span><h1>Documentos</h1>
        <p class="page-subtitle">Arquivos preservados e aguardando validação manual. Nenhum dado é importado automaticamente.</p></div>
    <?php if ($canManage): ?><a class="button button-primary" href="/documentos/novo"><span class="button-plus">+</span> Registrar documento</a><?php endif; ?>
</div>
<div class="notice notice-info"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
    <span><strong>Proteção de arquivos:</strong> originais enviados são criptografados antes de serem gravados. O conteúdo não é exibido nem analisado nesta fase.</span>
</div>
<section class="panel">
    <form class="filter-bar" action="/documentos" method="get">
        <label class="sr-only" for="doc-status">Situação</label>
        <select class="form-select filter-select" id="doc-status" name="status"><option value="">Todas as situações</option>
            <?php foreach ($statuses as $item): ?><option value="<?= h($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= h($statusLabels[$item]) ?></option><?php endforeach; ?>
        </select>
        <label class="sr-only" for="doc-type">Tipo</label>
        <select class="form-select filter-select" id="doc-type" name="type"><option value="">Todos os tipos</option>
            <?php foreach ($types as $item): ?><option value="<?= h($item) ?>" <?= $type === $item ? 'selected' : '' ?>><?= h($typeLabels[$item]) ?></option><?php endforeach; ?>
        </select>
        <label class="sr-only" for="doc-company">Empresa</label>
        <select class="form-select filter-select" id="doc-company" name="company_id"><option value="">Todas as empresas</option>
            <?php foreach ($companies as $company): ?><option value="<?= h($company['id']) ?>" <?= $companyId === (string) $company['id'] ? 'selected' : '' ?>><?= h($company['name']) ?> · #<?= h($company['id']) ?></option><?php endforeach; ?>
        </select>
        <button class="button button-secondary" type="submit">Filtrar</button>
    </form>
    <div class="list-meta"><span><strong><?= h($total) ?></strong> <?= $total === 1 ? 'documento' : 'documentos' ?></span>
        <span>O status “Validado” não importa o conteúdo.</span></div>
    <?php if ($documents === []): ?>
        <div class="empty-state compact-empty"><span class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
            <strong>O acervo está vazio</strong><p>Registre um documento original. Ele será armazenado criptografado e ficará pendente para revisão.</p>
            <?php if ($canManage): ?><a class="button button-secondary" href="/documentos/novo">Registrar documento</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Documento</th><th>Empresa</th><th>Tipo</th><th>Status</th><th>Hash SHA-256</th><th>Registrado em</th><?php if ($canManage): ?><th>Atualizar</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($documents as $document): ?>
                <tr>
                    <td><span class="table-primary-link"><?= h($document['original_filename']) ?></span>
                        <small><?= h(number_format((int) $document['file_size_bytes'] / 1024, 1, ',', '.')) ?> KB<?= (int) $document['is_current'] === 1 ? ' · Vigente' : ' · Não vigente' ?></small></td>
                    <td><?= $document['company_name'] ? h($document['company_name']) . ' · #' . h($document['company_id']) : 'Não vinculada' ?></td>
                    <td><span class="type-tag"><?= h($typeLabels[$document['document_type']] ?? 'Outro') ?></span></td>
                    <td><span class="status-badge status-<?= h($document['source_status']) ?>"><span></span><?= h($statusLabels[$document['source_status']] ?? $document['source_status']) ?></span></td>
                    <td><code class="hash-cell" title="<?= h($document['file_hash_sha256']) ?>"><?= h(substr($document['file_hash_sha256'], 0, 12)) ?>…</code></td>
                    <td><?= h(date('d/m/Y', strtotime($document['created_at']))) ?></td>
                    <?php if ($canManage): ?><td><form action="/documentos/<?= h($document['id']) ?>/status" method="post" class="inline-status-form">
                        <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                        <label class="sr-only" for="status-<?= h($document['id']) ?>">Status do documento</label>
                        <select id="status-<?= h($document['id']) ?>" class="form-select compact-select" name="source_status">
                            <?php foreach ($statuses as $item): ?><option value="<?= h($item) ?>" <?= $document['source_status'] === $item ? 'selected' : '' ?>><?= h($statusLabels[$item]) ?></option><?php endforeach; ?>
                        </select><button class="icon-button save-status" type="submit" title="Salvar status" aria-label="Salvar status">✓</button>
                    </form></td><?php endif; ?>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php if ($pages > 1): ?><nav class="pagination" aria-label="Paginação">
            <span>Página <?= h($page) ?> de <?= h($pages) ?></span><div>
                <?php $pageParams = ['status' => $status, 'type' => $type, 'company_id' => $companyId]; ?>
                <?php if ($page > 1): ?><a class="button button-quiet" href="/documentos?<?= h(http_build_query($pageParams + ['page' => $page - 1])) ?>">Anterior</a><?php endif; ?>
                <?php if ($page < $pages): ?><a class="button button-quiet" href="/documentos?<?= h(http_build_query($pageParams + ['page' => $page + 1])) ?>">Próxima</a><?php endif; ?>
            </div>
        </nav><?php endif; ?>
    <?php endif; ?>
</section>
