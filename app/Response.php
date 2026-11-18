<?php

declare(strict_types=1);

namespace NewDoc;

final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = []
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self($status, '', ['Location' => $location]);
    }
}
