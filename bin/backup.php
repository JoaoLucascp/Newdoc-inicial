<?php

declare(strict_types=1);

define('NEWDOC_ROOT', dirname(__DIR__));
require NEWDOC_ROOT . '/bootstrap.php';

try {
    $pdo = NewDoc\Database::connection();
    $directory = NEWDOC_ROOT . '/storage/backups';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível preparar o diretório de backup.');
    }

    $destination = $directory . DIRECTORY_SEPARATOR
        . 'newdoc-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sqlite';
    $documentSource = NEWDOC_ROOT . '/storage/documents';
    $documentBackup = null;
    if (is_dir($documentSource)) {
        $documentBackup = $destination . '.documents';
        if (!mkdir($documentBackup, 0700, true) && !is_dir($documentBackup)) {
            throw new RuntimeException('Não foi possível preparar a cópia dos documentos criptografados.');
        }
        foreach (glob($documentSource . '/*.enc') ?: [] as $sourceFile) {
            $targetFile = $documentBackup . DIRECTORY_SEPARATOR . basename($sourceFile);
            if (!copy($sourceFile, $targetFile)) {
                throw new RuntimeException('Não foi possível copiar um documento criptografado para o backup.');
            }
        }
    }

    $pdo->query('PRAGMA wal_checkpoint(FULL)');
    $quotedDestination = $pdo->quote($destination);
    $pdo->exec('VACUUM INTO ' . $quotedDestination);
    fwrite(STDOUT, 'Backup criado: ' . basename($destination) . PHP_EOL);
    if ($documentBackup !== null) {
        fwrite(STDOUT, 'Cópia de documentos criptografados: ' . basename($documentBackup) . PHP_EOL);
    }
    fwrite(STDOUT, "Lembre-se de guardar CREDENTIALS_KEY separadamente em local seguro.\n");
} catch (Throwable $exception) {
    if (isset($destination) && is_file($destination)) {
        unlink($destination);
    }
    if (isset($documentBackup) && is_dir($documentBackup)) {
        foreach (glob($documentBackup . DIRECTORY_SEPARATOR . '*.enc') ?: [] as $backupFile) {
            if (is_file($backupFile)) {
                unlink($backupFile);
            }
        }
        rmdir($documentBackup);
    }
    fwrite(STDERR, 'Falha ao criar backup: ' . get_class($exception) . PHP_EOL);
    exit(1);
}
