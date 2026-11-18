<?php

declare(strict_types=1);

namespace NewDoc;

use PDO;

final class Audit
{
    public static function record(
        PDO $pdo,
        string $action,
        string $table,
        ?int $recordId = null,
        ?string $field = null,
        ?int $companyId = null,
        bool $sensitive = false
    ): void {
        $statement = $pdo->prepare(
            'INSERT INTO audit_logs
                (user_id, action, table_name, record_id, field_name, sensitive_field,
                 company_id, ip_address, user_agent, created_at)
             VALUES
                (:user_id, :action, :table_name, :record_id, :field_name, :sensitive_field,
                 :company_id, :ip_address, :user_agent, :created_at)'
        );
        $statement->execute([
            'user_id' => Auth::user()['id'] ?? null,
            'action' => $action,
            'table_name' => $table,
            'record_id' => $recordId,
            'field_name' => $field,
            'sensitive_field' => $sensitive ? 1 : 0,
            'company_id' => $companyId,
            'ip_address' => self::clientIp(),
            'user_agent' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function clientIp(): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }
}
