<?php

declare(strict_types=1);

namespace NewDoc;

use RuntimeException;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            throw new RuntimeException('A sessão não pode ser iniciada após o envio de conteúdo.');
        }

        session_name('NEWDOCSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Config::bool('SESSION_SECURE_COOKIE', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        $lastActivity = (int) ($_SESSION['_last_activity'] ?? 0);
        if ($lastActivity > 0 && $lastActivity < time() - 1800) {
            $_SESSION = [];
            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Não foi possível renovar a sessão expirada.');
            }
        }
        $_SESSION['_last_activity'] = time();
    }

    public static function regenerate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !session_regenerate_id(true)) {
            throw new RuntimeException('Não foi possível renovar a sessão.');
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parameters['path'],
            'domain' => $parameters['domain'],
            'secure' => $parameters['secure'],
            'httponly' => $parameters['httponly'],
            'samesite' => $parameters['samesite'] ?? 'Lax',
        ]);
        session_destroy();
    }
}
