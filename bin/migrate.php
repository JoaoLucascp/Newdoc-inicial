<?php

declare(strict_types=1);

define('NEWDOC_ROOT', dirname(__DIR__));
require NEWDOC_ROOT . '/bootstrap.php';

try {
    $applied = NewDoc\MigrationRunner::migrate(NewDoc\Database::connection());
    if ($applied === []) {
        fwrite(STDOUT, "Banco atualizado; nenhuma migration pendente.\n");
        exit(0);
    }

    foreach ($applied as $migration) {
        fwrite(STDOUT, 'Aplicada: ' . $migration . PHP_EOL);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha ao migrar o banco: ' . get_class($exception) . PHP_EOL);
    exit(1);
}
