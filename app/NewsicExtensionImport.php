<?php

declare(strict_types=1);

namespace NewDoc;

use PDO;
use RuntimeException;
use Throwable;

final class NewsicExtensionImport
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function preview(int $instanceId, string $csv): array
    {
        $userId = $this->authorize();
        $this->instance($instanceId);
        $rows = NewsicExtensionCsv::parse($csv);
        $this->checkDuplicates($instanceId, $rows);
        $expires = time() + 600;
        $sealed = CredentialCipher::encrypt(json_encode([
            'version' => 1, 'user_id' => $userId, 'instance_id' => $instanceId,
            'expires' => $expires, 'csv' => $csv,
        ], JSON_THROW_ON_ERROR));
        $preview = [];
        foreach ($rows as $row) {
            $row['has_password'] = $row['registration_password'] !== '';
            unset($row['registration_password']);
            $preview[] = $row;
        }
        return ['rows' => $preview, 'sealed' => $sealed, 'expires' => $expires];
    }

    public function commit(int $instanceId, string $sealed): int
    {
        $userId = $this->authorize();
        if (strlen($sealed) > 12582912) throw new RuntimeException('Prévia inválida.');
        try {
            $draft = json_decode(CredentialCipher::decrypt($sealed), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Prévia inválida. Gere uma nova prévia.');
        }
        if (!is_array($draft) || ($draft['version'] ?? null) !== 1
            || ($draft['user_id'] ?? null) !== $userId || ($draft['instance_id'] ?? null) !== $instanceId
            || !is_int($draft['expires'] ?? null) || $draft['expires'] <= time()
            || !is_string($draft['csv'] ?? null)
        ) {
            throw new RuntimeException('Prévia inválida ou expirada. Gere uma nova prévia.');
        }
        $rows = NewsicExtensionCsv::parse($draft['csv']);
        unset($draft);
        CredentialCipher::assertAvailable();
        $this->pdo->beginTransaction();
        try {
            $instance = $this->instance($instanceId);
            $this->checkDuplicates($instanceId, $rows);
            $columns = array_keys($rows[0]);
            $columns[array_search('registration_password', $columns, true)] = 'registration_password_encrypted';
            $columns = array_merge($columns, ['newsic_instance_id', 'created_at', 'updated_at']);
            $insert = $this->pdo->prepare('INSERT INTO newsic_extensions (' . implode(', ', $columns) . ') VALUES ('
                . implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns)) . ')');
            $now = date('Y-m-d H:i:s');
            foreach ($rows as $row) {
                $password = $row['registration_password'];
                unset($row['registration_password']);
                $row['registration_password_encrypted'] = $password === '' ? null : CredentialCipher::encrypt($password);
                $hasPassword = $password !== '';
                unset($password);
                $insert->execute($row + ['newsic_instance_id' => $instanceId, 'created_at' => $now, 'updated_at' => $now]);
                $id = (int) $this->pdo->lastInsertId();
                Audit::record($this->pdo, 'criacao', 'newsic_extensions', $id, 'record', (int) $instance['company_id']);
                if ($hasPassword) Audit::record($this->pdo, 'criacao', 'newsic_extensions', $id, 'registration_password', (int) $instance['company_id'], true);
            }
            $this->pdo->commit();
            return count($rows);
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new RuntimeException('O lote não foi importado. Nenhum ramal deste lote foi gravado. Gere uma nova prévia e tente novamente.');
        }
    }

    private function authorize(): int
    {
        $id = (int) (Auth::user()['id'] ?? 0);
        if (!Auth::canManage() || $id < 1) throw new RuntimeException('Acesso negado.');
        return $id;
    }

    private function instance(int $id): array
    {
        if ($id < 1) throw new RuntimeException('Servidor não encontrado.');
        $query = $this->pdo->prepare('SELECT id, company_id FROM newsic_instances WHERE id = :id');
        $query->execute(['id' => $id]);
        $instance = $query->fetch(PDO::FETCH_ASSOC);
        if (!$instance) throw new RuntimeException('Servidor não encontrado.');
        return $instance;
    }

    private function checkDuplicates(int $instanceId, array $rows): void
    {
        $query = $this->pdo->prepare('SELECT 1 FROM newsic_extensions WHERE newsic_instance_id = :instance AND extension_number = :number');
        foreach ($rows as $row) {
            $query->execute(['instance' => $instanceId, 'number' => $row['extension_number']]);
            $exists = $query->fetchColumn() !== false;
            $query->closeCursor();
            if ($exists) throw new RuntimeException('O lote contém ramal já cadastrado neste servidor. Nenhum registro será sobrescrito.');
        }
    }
}
