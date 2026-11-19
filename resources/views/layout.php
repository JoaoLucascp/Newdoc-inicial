<?php

use NewDoc\Auth;
use NewDoc\Csrf;

$title = $title ?? 'Visão geral';
$active = $active ?? '';
$user = Auth::user();
$navItems = [
    ['label' => 'Visão geral', 'href' => '/', 'icon' => 'grid', 'key' => 'dashboard'],
    ['label' => 'Empresas', 'href' => '/empresas', 'icon' => 'building', 'key' => 'companies'],
    ['label' => 'Newsic / IPBX', 'href' => '/newsic', 'icon' => 'phone', 'key' => 'newsic'],
    ['label' => 'Newbot', 'href' => '/newbot', 'icon' => 'bot', 'key' => 'newbot'],
    ['label' => 'Documentos', 'href' => '/documentos', 'icon' => 'file', 'key' => 'documents'],
    ['label' => 'Auditoria', 'href' => '/auditoria', 'icon' => 'history', 'key' => 'audit'],
];
$icons = [
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
    'building' => '<path d="M3 21h18M5 21V5l7-2 7 2v16M9 8h.01M15 8h.01M9 12h.01M15 12h.01M10 21v-5h4v5"/>',
    'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 11.19 19a19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.09 4.18 2 2 0 0 1 4.08 2h3a2 2 0 0 1 2 1.72l.38 2.75a2 2 0 0 1-.57 1.68L7.6 9.43a16 16 0 0 0 6 6l1.28-1.28a2 2 0 0 1 1.68-.57l2.75.38a2 2 0 0 1 1.69 1.96Z"/>',
    'bot' => '<rect x="4" y="8" width="16" height="12" rx="2"/><path d="M12 4v4M9 13h.01M15 13h.01M9 17h6"/>',
    'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
    'history' => '<path d="M3 12a9 9 0 1 0 2.64-6.36L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title><?= h($title) ?> | NewDOC</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Navegação principal">
        <a class="brand" href="/" aria-label="NewDOC - início">
            <span class="brand-mark">N</span>
            <span class="brand-copy"><strong>NewDOC</strong><small>Gestão técnica</small></span>
        </a>
        <div class="sidebar-caption">MENU PRINCIPAL</div>
        <nav class="nav-list">
            <?php foreach ($navItems as $item): ?>
                <?php if ($item['key'] === 'audit' && !Auth::canManage()) { continue; } ?>
                <a class="nav-link <?= $active === $item['key'] ? 'is-active' : '' ?>"
                   href="<?= h($item['href']) ?>" <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icons[$item['icon']] ?></svg>
                    <span><?= h($item['label']) ?></span>
                    <?php if ($item['key'] === 'newsic'): ?><span class="nav-pill">F2</span><?php endif; ?>
                    <?php if ($item['key'] === 'newbot'): ?><span class="nav-pill">F3</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-bottom">
            <div class="phase-note">
                <span class="phase-dot"></span>
                <div><strong>Fase 3</strong><small>Módulos Newsic e Newbot</small></div>
            </div>
            <div class="sidebar-foot">Newtel Soluções<br>Ambiente local</div>
        </div>
    </aside>
    <div class="sidebar-scrim" data-sidebar-toggle></div>
    <main class="main-area">
        <header class="topbar">
            <button class="icon-button mobile-menu" type="button" aria-label="Abrir menu" aria-expanded="false"
                    aria-controls="sidebar" data-sidebar-toggle>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="topbar-heading">
                <span class="eyebrow">NEWTEL SOLUÇÕES</span>
                <span class="topbar-title"><?= h($title) ?></span>
            </div>
            <form class="global-search" action="/empresas" method="get" role="search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <label class="sr-only" for="global-q">Buscar empresa</label>
                <input id="global-q" type="search" name="q" placeholder="Buscar empresa, CNPJ, responsável..." maxlength="160">
                <kbd>Enter</kbd>
            </form>
            <div class="user-menu">
                <span class="avatar"><?= h(strtoupper(substr((string) ($user['name'] ?? 'U'), 0, 1))) ?></span>
                <span class="user-info"><strong><?= h($user['name'] ?? '') ?></strong>
                    <small><?= h(match ($user['role'] ?? '') {
                        'admin' => 'Administrador',
                        'technician' => 'Técnico',
                        default => 'Somente leitura',
                    }) ?></small></span>
                <form action="/logout" method="post">
                    <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                    <button class="icon-button logout-button" type="submit" aria-label="Sair" title="Sair">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                    </button>
                </form>
            </div>
        </header>
        <section class="page-content">
            <?php if ($flashSuccess !== null): ?><div class="alert alert-success" role="status"><?= h($flashSuccess) ?></div><?php endif; ?>
            <?= $content ?>
        </section>
        <footer class="app-footer"><span>NewDOC <span class="footer-separator">•</span> Fase 3</span><span>Dados protegidos e auditáveis</span></footer>
    </main>
</div>
</body>
</html>
