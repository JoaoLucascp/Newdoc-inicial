<?php

declare(strict_types=1);

namespace NewDoc;

use RuntimeException;

final class View
{
    public static function render(string $template, array $data = [], int $status = 200): Response
    {
        $templatePath = dirname(__DIR__) . '/resources/views/' . $template . '.php';
        if (!is_file($templatePath)) {
            throw new RuntimeException('A tela solicitada não está disponível.');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $templatePath;
        $content = (string) ob_get_clean();

        if ($template === 'auth/login') {
            return Response::html($content, $status);
        }

        $flashSuccess = Flash::pullSuccess();
        ob_start();
        require dirname(__DIR__) . '/resources/views/layout.php';

        return Response::html((string) ob_get_clean(), $status);
    }
}
