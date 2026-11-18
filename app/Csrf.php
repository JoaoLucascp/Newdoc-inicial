<?php

declare(strict_types=1);

namespace NewDoc;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function verify(?string $submittedToken): bool
    {
        return is_string($submittedToken)
            && isset($_SESSION['_csrf'])
            && is_string($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $submittedToken);
    }
}
