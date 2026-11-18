<?php

use NewDoc\Csrf;

$message = $message ?? null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Acesso | NewDOC</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="login-page">
<main class="login-layout">
    <section class="login-brand-panel">
        <div class="login-brand">
            <span class="brand-mark brand-mark-large">N</span>
            <div><strong>NewDOC</strong><span>Newtel Soluções</span></div>
        </div>
        <div class="login-brand-message">
            <span class="eyebrow eyebrow-light">GESTÃO TÉCNICA CENTRALIZADA</span>
            <h1>Informação organizada.<br>Operação mais segura.</h1>
            <p>Consulte empresas, acompanhe documentos de origem e mantenha um histórico confiável das alterações.</p>
        </div>
        <div class="login-panel-footer">Acesso restrito a usuários autorizados</div>
    </section>
    <section class="login-form-panel">
        <div class="login-card">
            <span class="login-mobile-brand">NEWDOC</span>
            <span class="eyebrow">BEM-VINDO(A)</span>
            <h2>Entrar na sua conta</h2>
            <p class="muted">Use seu e-mail corporativo para continuar.</p>
            <?php if ($message !== null): ?>
                <div class="alert alert-error" role="alert"><?= h($message) ?></div>
            <?php endif; ?>
            <form action="/login" method="post" class="form-stack">
                <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                <label class="field-label" for="email">E-mail</label>
                <input class="form-input" id="email" name="email" type="email" autocomplete="username"
                       maxlength="254" required autofocus>
                <label class="field-label" for="password">Senha</label>
                <input class="form-input" id="password" name="password" type="password"
                       autocomplete="current-password" required>
                <button class="button button-primary button-full" type="submit">Entrar no sistema
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>
            <p class="login-security"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                Conexão protegida. Suas credenciais nunca são registradas em auditoria.</p>
        </div>
    </section>
</main>
</body>
</html>
