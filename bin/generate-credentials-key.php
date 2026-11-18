<?php

declare(strict_types=1);

if (!function_exists('sodium_crypto_secretbox')) {
    fwrite(STDERR, "Ative a extensão Sodium do PHP antes de gerar a chave.\n");
    exit(1);
}

fwrite(STDERR, "Guarde esta chave em um cofre/variável de ambiente. Ela não pode ser recuperada do banco.\n");
fwrite(STDOUT, base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)) . PHP_EOL);
