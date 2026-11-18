<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    NewDoc\Session::start();
    $application = new NewDoc\Application(NewDoc\Database::connection());
    $response = $application->handle();
} catch (Throwable $exception) {
    $reference = bin2hex(random_bytes(6));
    error_log('[NewDOC][' . $reference . '] ' . get_class($exception));
    $response = NewDoc\Response::html(
        '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
        . '<title>Erro interno | NewDOC</title><link rel="stylesheet" href="/assets/app.css">'
        . '<body><main class="error-card"><span class="error-code">500</span>'
        . '<h1>Não foi possível concluir a solicitação</h1>'
        . '<p>Informe esta referência ao suporte: <code>' . h($reference) . '</code></p>'
        . '<a class="button button-primary" href="/">Voltar ao início</a></main></body></html>',
        500
    );
}

http_response_code($response->status);
foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\'; script-src \'self\'; img-src \'self\' data:; form-action \'self\'; frame-ancestors \'none\'; base-uri \'self\'');
echo $response->body;
