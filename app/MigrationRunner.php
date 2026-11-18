<?php

declare(strict_types=1);

namespace NewDoc;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public static function migrate(PDO $pdo): array
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );
        $directory = dirname(__DIR__) . '/database/migrations';
        $files = glob($directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $applied = [];

        foreach ($files as $file) {
            $version = basename($file);
            $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
            $check->execute(['version' => $version]);
            if ($check->fetchColumn()) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('Migration vazia ou ilegível: ' . $version);
            }

            $pdo->beginTransaction();
            try {
                $pdo->exec($sql);
                $insert = $pdo->prepare(
                    'INSERT INTO schema_migrations (version, applied_at)
                     VALUES (:version, :applied_at)'
                );
                $insert->execute(['version' => $version, 'applied_at' => date('Y-m-d H:i:s')]);
                $pdo->commit();
                $applied[] = $version;
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $exception;
            }
        }

        return $applied;
    }
}
