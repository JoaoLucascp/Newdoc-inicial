<?php

$title = $company['name'];
$active = 'companies';
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'inexistente' => 'Sem contrato'];
$newsicStatus = null;
foreach ($newsicInstances as $newsicInstance) {
    if ($newsicInstance['status'] === 'ativo') {
        $newsicStatus = $newsicInstance;
        break;
    }
}
?>
<div class="page-heading">
    <div><a class="back-link" href="/empresas">← Voltar para empresas</a>
        <span class="eyebrow">FICHA DA EMPRESA · ID <?= h($company['id']) ?></span>
        <h1><?= h($company['name']) ?></h1>
        <?php if ($company['trade_name']): ?><p class="page-subtitle"><?= h($company['trade_name']) ?></p><?php endif; ?>
    </div>
    <?php if ($canManage): ?><a class="button button-secondary" href="/empresas/<?= h($company['id']) ?>/editar">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m16 4 4 4M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16z"/></svg> Editar empresa
    </a><?php endif; ?>
</div>
<div class="detail-grid">
    <section class="panel detail-main">
        <div class="panel-heading"><div><h2>Informações cadastrais</h2><p>Dados de identificação e contato</p></div>
            <span class="status-badge status-<?= h($company['contract_status']) ?>"><span></span><?= h($statusLabels[$company['contract_status']] ?? 'Não informado') ?></span>
        </div>
        <dl class="detail-list">
            <div><dt>CNPJ</dt><dd><?= h($company['cnpj'] ?: 'Não informado') ?></dd></div>
            <div><dt>Responsável</dt><dd><?= h($company['responsible_name'] ?: 'Não informado') ?></dd></div>
            <div><dt>Telefone</dt><dd><?= h($company['responsible_phone'] ?: 'Não informado') ?></dd></div>
            <div><dt>E-mail</dt><dd><?= h($company['email'] ?: 'Não informado') ?></dd></div>
            <div class="detail-wide"><dt>Endereço</dt><dd><?= nl2br(h($company['address'] ?: 'Não informado')) ?></dd></div>
            <div><dt>Início do contrato</dt><dd><?= $company['contract_start_date'] ? h(date('d/m/Y', strtotime($company['contract_start_date']))) : '—' ?></dd></div>
            <div><dt>Fim do contrato</dt><dd><?= $company['contract_end_date'] ? h(date('d/m/Y', strtotime($company['contract_end_date']))) : '—' ?></dd></div>
            <?php if ($company['notes']): ?><div class="detail-wide"><dt>Observações</dt><dd><?= nl2br(h($company['notes'])) ?></dd></div><?php endif; ?>
        </dl>
        <div class="detail-meta"><span>Criada em <?= h(date('d/m/Y H:i', strtotime($company['created_at']))) ?></span>
            <span>Atualizada em <?= h(date('d/m/Y H:i', strtotime($company['updated_at']))) ?></span></div>
    </section>
    <aside class="detail-side">
        <section class="panel side-card">
            <div class="panel-heading"><div><h2>Serviços</h2><p>Módulos da empresa</p></div></div>
            <div class="future-module"><span class="module-icon icon-blue">N</span><span><strong>Newsic / IPBX</strong><small><?= $newsicStatus ? h($newsicStatus['server_name']) : 'Configurações técnicas' ?></small></span>
                <?php if ($newsicStatus): ?><span class="status-badge status-ativo"><span></span>Ativo</span>
                <?php elseif ($newsicInstances !== []): ?><span class="status-badge status-muted"><span></span>Histórico</span>
                <?php else: ?><span class="status-badge status-muted"><span></span>Não cadastrado</span><?php endif; ?>
                <a class="text-link" href="/empresas/<?= h($company['id']) ?>/newsic">Abrir</a>
            </div>
            <div class="future-module"><span class="module-icon icon-purple">B</span><span><strong>Newbot</strong><small>Instâncias, canais e integrações</small></span>
                <a class="text-link" href="/empresas/<?= h($company['id']) ?>/newbot">Abrir</a>
            </div>
            <div class="future-module"><span class="module-icon icon-amber">V</span><span><strong>Newvoz</strong><small>Módulo de fase futura</small></span><span class="status-badge status-muted"><span></span>Indisponível</span></div>
        </section>
        <section class="panel side-card">
            <div class="panel-heading"><div><h2>Documentos</h2><p>Arquivos de origem vinculados</p></div><a class="text-link" href="/documentos?company_id=<?= h($company['id']) ?>">Ver todos</a></div>
            <?php if ($documents === []): ?><div class="empty-inline">Nenhum documento vinculado.</div>
            <?php else: ?><div class="mini-list">
                <?php foreach ($documents as $document): ?><a class="mini-list-item" href="/documentos?company_id=<?= h($company['id']) ?>">
                    <span class="mini-file"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
                    <span><strong><?= h($document['original_filename']) ?></strong><small><?= h(strtoupper($document['document_type'])) ?> · <?= h($document['source_status']) ?></small></span>
                </a><?php endforeach; ?>
            </div><?php endif; ?>
        </section>
    </aside>
</div>
<section class="panel audit-company-panel">
    <div class="panel-heading"><div><h2>Histórico recente</h2><p>Eventos auditados para esta empresa</p></div><a class="text-link" href="/auditoria">Abrir auditoria</a></div>
    <?php if ($auditEvents === []): ?><div class="empty-inline">Ainda não há eventos para esta empresa.</div>
    <?php else: ?><div class="table-wrap"><table class="data-table">
        <thead><tr><th>Ação</th><th>Registro</th><th>Campo</th><th>Data e hora</th></tr></thead>
        <tbody><?php foreach ($auditEvents as $event): ?><tr>
            <td><?= h(ucfirst($event['action'])) ?></td><td><?= h($event['table_name']) ?> #<?= h($event['record_id'] ?? '—') ?></td>
            <td><?= h($event['field_name'] ?? '—') ?></td><td><?= h(date('d/m/Y H:i', strtotime($event['created_at']))) ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</section>
