<?php

declare(strict_types=1);

if (!defined('NEWDOC_ROOT')) {
    define('NEWDOC_ROOT', __DIR__);
}

if (is_file(NEWDOC_ROOT . '/vendor/autoload.php')) {
    require_once NEWDOC_ROOT . '/vendor/autoload.php';
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'NewDoc\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = NEWDOC_ROOT . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

NewDoc\Config::load(NEWDOC_ROOT . '/.env');
date_default_timezone_set(NewDoc\Config::get('APP_TIMEZONE', 'America/Sao_Paulo') ?? 'America/Sao_Paulo');

if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
