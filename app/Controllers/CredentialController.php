<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use NewDoc\Audit;
use NewDoc\Auth;
use NewDoc\CredentialCipher;
use NewDoc\Csrf;
use NewDoc\Response;
use PDO;
use Throwable;

final class CredentialController
{
    private const TARGETS = [
        'newsic_web' => ['newsic_instances', 'web_password_encrypted', 'web_password', null],
        'newsic_ssh' => ['newsic_instances', 'ssh_password_encrypted', 'ssh_password', null],
        'newsic_extension' => ['newsic_extensions', 'registration_password_encrypted', 'registration_password', 'newsic_instances'],
        'newbot_administrator' => ['newbot_administrators', 'password_encrypted', 'password', 'newbot_instances'],
        'newbot_integration' => ['newbot_integrations', 'password_encrypted', 'password', 'newbot_instances'],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function reveal(string $target, int $id): Response
    {
        if (!Auth::canManage()) {
            return $this->json(['error' => 'Acesso negado.'], 403);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return $this->json(['error' => 'Método não permitido.'], 405);
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            return $this->json(['error' => 'Sessão ou formulário inválido.'], 419);
        }
        if (!isset(self::TARGETS[$target]) || $id < 1) {
            return $this->json(['error' => 'Credencial não encontrada.'], 404);
        }
        [$table, $column, $field, $parent] = self::TARGETS[$target];
        $join = '';
        $company = 'r.company_id';
        if ($parent !== null) {
            $foreignKey = $parent === 'newsic_instances' ? 'newsic_instance_id' : 'newbot_instance_id';
            $join = ' JOIN ' . $parent . ' i ON i.id = r.' . $foreignKey;
            $company = 'i.company_id';
        }
        try {
            $statement = $this->pdo->prepare(
                'SELECT r.' . $column . ' AS credential, ' . $company . ' AS company_id FROM '
                . $table . ' r' . $join . ' WHERE r.id = :id'
            );
            $statement->execute(['id' => $id]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['credential'] === null) {
                return $this->json(['error' => 'Credencial não encontrada.'], 404);
            }
            $secret = CredentialCipher::decrypt($row['credential']);
            Audit::record($this->pdo, 'consulta', $table, $id, $field, (int) $row['company_id'], true);
            return $this->json(['value' => $secret]);
        } catch (Throwable) {
            return $this->json(['error' => 'Não foi possível consultar a credencial.'], 500);
        }
    }

    private function json(array $data, int $status = 200): Response
    {
        return new Response($status, json_encode($data, JSON_THROW_ON_ERROR), [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
