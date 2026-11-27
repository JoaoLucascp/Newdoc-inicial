<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

require_once dirname(__DIR__) . '/app/Config.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Audit.php';
require_once dirname(__DIR__) . '/app/Flash.php';
require_once dirname(__DIR__) . '/app/Response.php';
require_once dirname(__DIR__) . '/app/View.php';
require_once dirname(__DIR__) . '/app/CredentialCipher.php';
require_once dirname(__DIR__) . '/app/MigrationRunner.php';
require_once dirname(__DIR__) . '/app/Controllers/NewbotController.php';

use NewDoc\Controllers\NewbotController;
use NewDoc\MigrationRunner;

$assert = static function (bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException('Falha exclus�o Newbot: ' . $label);
    }
};
$at = '2026-01-01 00:00:00';
$previousKey = getenv('CREDENTIALS_KEY');
$previousSession = $_SESSION ?? [];
try {
    putenv('CREDENTIALS_KEY=' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    $_SESSION = ['user' => ['id' => 1, 'role' => 'technician']];
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
    foreach (['001_initial_schema.sql', '002_newsic_schema.sql', '003_newbot_schema.sql', '004_newbot_management.sql'] as $migration) {
        $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/migrations/' . $migration));
        $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')->execute([$migration, $at]);
    }
    $pdo->exec("INSERT INTO companies (id, name, created_at, updated_at) VALUES (1, 'Empresa', '$at', '$at')");
    $pdo->exec("INSERT INTO users (id, name, email, password_hash, role, created_at, updated_at) VALUES (1, 'Teste', 'teste@example.invalid', 'hash-ficticio', 'technician', '$at', '$at')");
    $pdo->exec("INSERT INTO newbot_instances (id, company_id, instance_name, status, created_at, updated_at) VALUES (1, 1, 'A', 'ativo', '$at', '$at'), (2, 1, 'B', 'inativo', '$at', '$at')");
    $pdo->exec("INSERT INTO newbot_channels (id, newbot_instance_id, channel_name, status, created_at, updated_at) VALUES (1, 1, 'Canal A', 'ativo', '$at', '$at'), (2, 2, 'Canal B', 'ativo', '$at', '$at')");
    $pdo->exec("INSERT INTO newbot_supervisors (id, newbot_instance_id, name, login, email, created_at, updated_at) VALUES (1, 1, 'Sup A', 'sup1', 'sup1@example.invalid', '$at', '$at'), (2, 2, 'Sup B', 'sup2', 'sup2@example.invalid', '$at', '$at')");
    $controller = new NewbotController($pdo);

    $_SESSION['user']['role'] = 'viewer';
    $_POST = ['_csrf' => 'x', 'section' => 'channels', 'record_id' => '1'];
    $assert($controller->deleteChild(1)->status === 403, 'viewer bloqueado');
    $_SESSION['user']['role'] = 'technician';

    $_POST = ['_csrf' => 'x', 'section' => 'invalido', 'record_id' => '1'];
    $assert($controller->deleteChild(1)->status === 400, 'se��o inv�lida');
    $_POST = ['_csrf' => 'x', 'section' => 'channels', 'record_id' => 'abc'];
    $assert($controller->deleteChild(1)->status === 400, 'record_id inv�lido');
    $_POST = ['_csrf' => 'x', 'section' => 'channels', 'record_id' => '2'];
    $assert($controller->deleteChild(1)->status === 404, 'isolamento por inst�ncia');

    $_POST = ['_csrf' => 'x', 'section' => 'channels', 'record_id' => '1'];
    $assert($controller->deleteChild(1)->status === 303, 'exclus�o de canal');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM newbot_channels')->fetchColumn() === 1, 'canal removido');
    $audit = $pdo->query("SELECT sensitive_field FROM audit_logs WHERE action = 'exclusao' AND table_name = 'newbot_channels' AND record_id = 1")->fetchColumn();
    $assert($audit !== false && (int) $audit === 0, 'auditoria de canal n�o sens�vel');

    $_POST = ['_csrf' => 'x', 'section' => 'supervisors', 'record_id' => '1'];
    $assert($controller->deleteChild(1)->status === 303, 'exclus�o de supervisor');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM newbot_supervisors')->fetchColumn() === 1, 'supervisor removido');
    $audit = $pdo->query("SELECT sensitive_field FROM audit_logs WHERE action = 'exclusao' AND table_name = 'newbot_supervisors' AND record_id = 1")->fetchColumn();
    $assert($audit !== false && (int) $audit === 1, 'auditoria de supervisor sens�vel');

    $_POST = ['_csrf' => 'x', 'section' => 'channels', 'record_id' => '1'];
    $assert($controller->deleteChild(1)->status === 404, 'segunda exclus�o recusada');

    echo "OK: exclus�o de registros do Newbot com confirma��o, autoriza��o, isolamento e auditoria.\n";
} finally {
    if ($previousKey === false) {
        putenv('CREDENTIALS_KEY');
    } else {
        putenv('CREDENTIALS_KEY=' . $previousKey);
    }
    $_SESSION = $previousSession;
}