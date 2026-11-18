<?php

declare(strict_types=1);

namespace NewDoc;

use PDO;

final class LoginThrottle
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function isBlocked(string $email, ?string $ip): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT failed_attempts, last_failed_at
             FROM login_attempts
             WHERE email = :email AND ip_address = :ip'
        );
        $statement->execute(['email' => $email, 'ip' => $ip ?? '']);
        $attempt = $statement->fetch();
        if (
            $attempt
            && (int) $attempt['failed_attempts'] >= self::MAX_ATTEMPTS
            && strtotime((string) $attempt['last_failed_at']) > time() - self::WINDOW_MINUTES * 60
        ) {
            return true;
        }

        if ($ip === null) {
            return false;
        }

        $ipAttempts = $this->pdo->prepare(
            'SELECT COALESCE(SUM(failed_attempts), 0)
             FROM login_attempts
             WHERE ip_address = :ip AND last_failed_at > :since'
        );
        $ipAttempts->execute([
            'ip' => $ip,
            'since' => date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60),
        ]);

        return (int) $ipAttempts->fetchColumn() >= 20;
    }

    public function recordFailure(string $email, ?string $ip): void
    {
        $key = ['email' => $email, 'ip' => $ip ?? ''];
        $cleanup = $this->pdo->prepare('DELETE FROM login_attempts WHERE last_failed_at < :cutoff');
        $cleanup->execute(['cutoff' => date('Y-m-d H:i:s', time() - 86400)]);
        $statement = $this->pdo->prepare(
            'SELECT failed_attempts, first_failed_at, last_failed_at
             FROM login_attempts
             WHERE email = :email AND ip_address = :ip'
        );
        $statement->execute($key);
        $current = $statement->fetch();
        $now = date('Y-m-d H:i:s');

        if (!$current || strtotime((string) $current['last_failed_at']) <= time() - self::WINDOW_MINUTES * 60) {
            $upsert = $this->pdo->prepare(
                'INSERT INTO login_attempts (email, ip_address, failed_attempts, first_failed_at, last_failed_at)
                 VALUES (:email, :ip, 1, :now, :now)
                 ON CONFLICT(email, ip_address) DO UPDATE SET
                    failed_attempts = 1, first_failed_at = excluded.first_failed_at,
                    last_failed_at = excluded.last_failed_at'
            );
            $upsert->execute($key + ['now' => $now]);
            return;
        }

        $update = $this->pdo->prepare(
            'UPDATE login_attempts
             SET failed_attempts = failed_attempts + 1, last_failed_at = :now
             WHERE email = :email AND ip_address = :ip'
        );
        $update->execute($key + ['now' => $now]);
    }

    public function clear(string $email, ?string $ip): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip'
        );
        $statement->execute(['email' => $email, 'ip' => $ip ?? '']);
    }
}
