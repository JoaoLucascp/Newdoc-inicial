<?php

declare(strict_types=1);

namespace NewDoc;

final class PasswordPolicy
{
    public static function isValid(string $password): bool
    {
        if ($password === '' || $password[0] === ' ' || substr($password, -1) === ' ') {
            return false;
        }

        $characters = [];
        $length = preg_match_all('/./us', $password, $characters);

        return $length !== false
            && $length >= 6
            && $length <= 12
            && preg_match('~\A[\p{L}\p{M}\p{N}!@#$%^&*()_\-+={}\[\]:;,.?/\~ ]+\z~u', $password) === 1;
    }
}
