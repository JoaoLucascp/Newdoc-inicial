<?php

declare(strict_types=1);

namespace NewDoc;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $configuredPath = Config::get('DB_PATH', 'storage/database/newdoc.sqlite');
        if ($configuredPath === null || $configuredPath === '') {
            throw new RuntimeException('DB_PATH não foi configurado.');
        }

        $databasePath = str_starts_with($configuredPath, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:\\\\/', $configuredPath) === 1
            ? $configuredPath
            : dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $configuredPath
            );
        $directory = dirname($databasePath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o diretório do banco de dados.');
        }

        $pdo = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        self::$connection = $pdo;

        return $pdo;
    }

    public static function resetConnection(): void
    {
        self::$connection = null;
    }
}
