<?php

declare(strict_types=1);

namespace NewDoc;

use RuntimeException;

final class CredentialCipher
{
    public static function assertAvailable(): void
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('A extensão Sodium do PHP é obrigatória para criptografar credenciais.');
        }

        self::key();
    }

    public static function encrypt(string $plaintext): string
    {
        self::assertAvailable();

        $nonceBytes = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        $nonce = random_bytes($nonceBytes);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, self::key());

        return 'v1:' . base64_encode($nonce . $ciphertext);
    }

    public static function decrypt(string $payload): string
    {
        if (!function_exists('sodium_crypto_secretbox_open')) {
            throw new RuntimeException('A extensão Sodium do PHP é obrigatória para descriptografar credenciais.');
        }
        self::key();
        if (!str_starts_with($payload, 'v1:')) {
            throw new RuntimeException('Formato de credencial criptografada não reconhecido.');
        }

        $decoded = base64_decode(substr($payload, 3), true);
        $nonceBytes = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if ($decoded === false || strlen($decoded) <= $nonceBytes) {
            throw new RuntimeException('Credencial criptografada inválida.');
        }

        $nonce = substr($decoded, 0, $nonceBytes);
        $ciphertext = substr($decoded, $nonceBytes);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, self::key());
        if ($plaintext === false) {
            throw new RuntimeException('Não foi possível validar a credencial criptografada ou a chave configurada.');
        }

        return $plaintext;
    }

    private static function key(): string
    {
        $configuredKey = Config::get('CREDENTIALS_KEY');
        if ($configuredKey === null || $configuredKey === '') {
            throw new RuntimeException('CREDENTIALS_KEY não está configurada no ambiente.');
        }

        $key = base64_decode($configuredKey, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('CREDENTIALS_KEY deve ser Base64 de uma chave Sodium válida.');
        }

        return $key;
    }
}
