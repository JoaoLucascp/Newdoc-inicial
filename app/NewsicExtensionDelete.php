<?php

declare(strict_types=1);

namespace NewDoc;

use PDO;
use RuntimeException;
use Throwable;

final class NewsicExtensionDelete
{
    public function __construct(private readonly PDO $pdo) {}

    public function preview(int $instanceId, array $ids): array
    {
        $userId = $this->authorize();
        $ids = $this->ids($ids);
        $snapshot = $this->snapshot($instanceId, $ids);
        $expires = time() + 600;
        $sealed = CredentialCipher::encrypt(json_encode([
            'version' => 1, 'purpose' => 'delete_newsic_extensions', 'user_id' => $userId,
            'instance_id' => $instanceId, 'ids' => $ids, 'expires' => $expires,
            'fingerprint' => $this->fingerprint($snapshot),
        ], JSON_THROW_ON_ERROR));
        return [
            'instance' => $snapshot['instance'],
            'rows' => array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'extension_number' => $row['extension_number']], $snapshot['rows']),
            'sealed' => $sealed, 'expires' => $expires,
        ];
    }

    public function commit(int $instanceId, string $sealed, bool $confirmed = false): int
    {
        $userId = $this->authorize();
        if (!$confirmed) throw new RuntimeException('Confirme explicitamente a exclusão.');
        if (strlen($sealed) > 65536) throw new RuntimeException('Confirmação inválida.');
        try {
            $draft = json_decode(CredentialCipher::decrypt($sealed), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Confirmação inválida. Gere uma nova prévia.');
        }
        if (!is_array($draft) || ($draft['version'] ?? null) !== 1 || ($draft['purpose'] ?? '') !== 'delete_newsic_extensions'
            || ($draft['user_id'] ?? null) !== $userId || ($draft['instance_id'] ?? null) !== $instanceId
            || !is_int($draft['expires'] ?? null) || $draft['expires'] <= time()
            || !is_array($draft['ids'] ?? null) || !is_string($draft['fingerprint'] ?? null)
        ) throw new RuntimeException('Confirmação inválida ou expirada. Gere uma nova prévia.');
        $ids = $this->ids($draft['ids']);
        if ($this->pdo->inTransaction()) throw new RuntimeException('Operação indisponível durante outra transação.');
        $this->pdo->beginTransaction();
        try {
            $snapshot = $this->snapshot($instanceId, $ids);
            if (!hash_equals($draft['fingerprint'], $this->fingerprint($snapshot))) {
                throw new RuntimeException('Os registros mudaram após a prévia.');
            }
            $statement = $this->pdo->prepare('DELETE FROM newsic_extensions WHERE newsic_instance_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $statement->execute(array_merge([$instanceId], $ids));
            if ($statement->rowCount() !== count($ids)) throw new RuntimeException('Seleção alterada.');
            foreach ($ids as $id) {
                Audit::record($this->pdo, 'exclusao', 'newsic_extensions', $id, 'record', (int) $snapshot['instance']['company_id'], true);
            }
            $this->pdo->commit();
            return count($ids);
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new RuntimeException('Nenhum ramal foi excluído. A seleção pode ter mudado; gere uma nova prévia.');
        }
    }

    private function authorize(): int
    {
        $id = (int) (Auth::user()['id'] ?? 0);
        if (!Auth::canManage() || $id < 1) throw new RuntimeException('Acesso negado.');
        return $id;
    }

    private function ids(array $ids): array
    {
        if ($ids === [] || count($ids) > 500) throw new RuntimeException('Selecione de 1 a 500 ramais.');
        $normalized = [];
        foreach ($ids as $id) {
            if ((!is_int($id) && !is_string($id)) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new RuntimeException('Seleção inválida.');
            }
            $normalized[] = (int) $id;
        }
        if (count(array_unique($normalized)) !== count($normalized)) throw new RuntimeException('Seleção duplicada.');
        sort($normalized, SORT_NUMERIC);
        return $normalized;
    }

    private function snapshot(int $instanceId, array $ids): array
    {
        $query = $this->pdo->prepare('SELECT n.id, n.company_id, n.server_name, c.name AS company_name FROM newsic_instances n JOIN companies c ON c.id = n.company_id WHERE n.id = :id');
        $query->execute(['id' => $instanceId]);
        $instance = $query->fetch(PDO::FETCH_ASSOC);
        $query->closeCursor();
        if (!$instance) throw new RuntimeException('Servidor não encontrado.');
        $query = $this->pdo->prepare('SELECT * FROM newsic_extensions WHERE newsic_instance_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id');
        $query->execute(array_merge([$instanceId], $ids));
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        $query->closeCursor();
        if (count($rows) !== count($ids)) throw new RuntimeException('Há ramal inexistente ou pertencente a outro servidor na seleção.');
        return ['instance' => $instance, 'rows' => $rows];
    }

    private function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
