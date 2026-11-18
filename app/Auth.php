<?php

declare(strict_types=1);

namespace NewDoc;

final class Auth
{
    public static function user(): ?array
    {
        $user = $_SESSION['user'] ?? null;

        return is_array($user) ? $user : null;
    }

    public static function canManage(): bool
    {
        return in_array(self::user()['role'] ?? null, ['admin', 'technician'], true);
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }
}
