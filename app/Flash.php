<?php

declare(strict_types=1);

namespace NewDoc;

final class Flash
{
    public static function success(string $message): void
    {
        $_SESSION['_flash_success'] = $message;
    }

    public static function pullSuccess(): ?string
    {
        $message = $_SESSION['_flash_success'] ?? null;
        unset($_SESSION['_flash_success']);

        return is_string($message) ? $message : null;
    }
}
