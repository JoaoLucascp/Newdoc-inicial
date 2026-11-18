<?php

declare(strict_types=1);

namespace NewDoc;

final class Validator
{
    public static function text(?string $value, int $maxBytes): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return strlen($value) <= $maxBytes ? $value : null;
    }

    public static function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }

        return $value;
    }

    public static function email(?string $value): ?string
    {
        $value = self::text($value, 254);
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? strtolower($value) : null;
    }
}
