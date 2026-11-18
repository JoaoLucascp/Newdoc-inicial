<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$decodedPath = rawurldecode($path);
if (
    str_starts_with($decodedPath, '/assets/')
    && !str_contains($decodedPath, '..')
    && !str_contains($decodedPath, "\0")
) {
    $assetRoot = realpath(__DIR__ . '/assets');
    $assetPath = realpath(__DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $decodedPath));
    if (
        $assetRoot !== false
        && $assetPath !== false
        && str_starts_with($assetPath, $assetRoot . DIRECTORY_SEPARATOR)
        && is_file($assetPath)
    ) {
        return false;
    }
}

require __DIR__ . '/index.php';
