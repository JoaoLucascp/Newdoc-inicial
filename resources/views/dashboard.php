<?php

use NewDoc\Auth;

$title = 'Visão geral';
$active = 'dashboard';
$canManage = $canManage ?? false;
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'inexistente' => 'Sem contrato'];
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">PAINEL DE CONTROLE</span>
        <h1>Olá, <?= h(explode(' ', Auth::user()['name'] ?? '')[0]) ?> <span class="wave">👋</span></h1>
        <p class="page-subtitle">Acompanhe o cadastro e a documentação técnica em um só lugar.</p>
    </div>
    <?php if ($canManage): ?>
        <a class="button button-primary" href="/empresas/nova">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nova empresa
        </a>
    <?php endif; ?>
</div>
<form class="hero-search" action="/empresas" method="get" role="search">
    <span class="hero-search-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></span>
    <label class="sr-only" for="company-search">Buscar empresa</label>
    <input id="company-search" type="search" name="q" maxlength="160"
           placeholder="Buscar por empresa, CNPJ, e-mail ou responsável...">
    <button class="button button-primary" type="submit">Buscar</button>
</form>
<div class="section-heading">
    <div><h2>Resumo do acervo</h2><p>Indicadores do ambiente NewDOC</p></div>
    <span class="updated-label"><span class="status-dot dot-green"></span> Atualizado agora</span>
</div>
<div class="metric-grid">
    <a class="metric-card" href="/empresas">
        <span class="metric-icon icon-blue"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V5l7-2 7 2v16M9 8h.01M15 8h.01M9 12h.01M15 12h.01"/></svg></span>
        <span class="metric-label">Empresas cadastradas</span>
        <strong class="metric-value"><?= h($metrics['companies']) ?></strong>
        <span class="metric-foot"><?= h($metrics['active_companies']) ?> com contrato ativo</span>
    </a>
    <a class="metric-card" href="/empresas?status=ativo">
        <span class="metric-icon icon-green"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span>
        <span class="metric-label">Contratos ativos</span>
        <strong class="metric-value"><?= h($metrics['active_companies']) ?></strong>
        <span class="metric-foot">Situação contratual</span>
    </a>
    <a class="metric-card" href="/documentos">
        <span class="metric-icon icon-amber"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg></span>
        <span class="metric-label">Documentos pendentes</span>
        <strong class="metric-value"><?= h($metrics['pending_documents']) ?></strong>
        <span class="metric-foot">Aguardando análise manual</span>
    </a>
    <a class="metric-card" href="/documentos">
        <span class="metric-icon icon-purple"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/><circle cx="7" cy="7" r="1"/><circle cx="7" cy="12" r="1"/><circle cx="7" cy="17" r="1"/></svg></span>
        <span class="metric-label">Documentos no acervo</span>
        <strong class="metric-value"><?= h($metrics['documents']) ?></strong>
        <span class="metric-foot">Arquivos protegidos</span>
    </a>
    <a class="metric-card" href="/newsic">
        <span class="metric-icon icon-blue"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-4l-3 3-3-3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M8 9h8M8 13h5"/></svg></span>
        <span class="metric-label">Servidores Newsic ativos</span>
        <strong class="metric-value"><?= h($metrics['active_newsic_instances']) ?></strong>
        <span class="metric-foot">Telefonia IPBX</span>
    </a>
</div>
<section class="panel">
    <div class="panel-heading">
        <div><h2>Empresas recentes</h2><p>Últimos cadastros atualizados</p></div>
        <a class="text-link" href="/empresas">Ver todas <span aria-hidden="true">→</span></a>
    </div>
    <?php if ($recentCompanies === []): ?>
        <div class="empty-state">
            <span class="empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V5l7-2 7 2v16M9 8h.01M15 8h.01"/></svg></span>
            <strong>Seu acervo começa aqui</strong>
            <p>Cadastre a primeira empresa para organizar suas informações.</p>
            <?php if ($canManage): ?><a class="button button-secondary" href="/empresas/nova">Cadastrar empresa</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Empresa</th><th>Situação do contrato</th><th>Última atualização</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentCompanies as $company): ?>
                    <tr>
                        <td><a class="table-primary-link" href="/empresas/<?= h($company['id']) ?>"><?= h($company['name']) ?></a>
                            <?php if ($company['trade_name']): ?><small><?= h($company['trade_name']) ?></small><?php endif; ?></td>
                        <td><span class="status-badge status-<?= h($company['contract_status']) ?>"><span></span><?= h($statusLabels[$company['contract_status']] ?? 'Não informado') ?></span></td>
                        <td><?= h(date('d/m/Y', strtotime($company['updated_at']))) ?></td>
                        <td><a class="row-arrow" href="/empresas/<?= h($company['id']) ?>" aria-label="Abrir empresa">→</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
