<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

foreach (['Config', 'Auth', 'Audit', 'Flash', 'Response', 'View', 'Csrf', 'CredentialCipher', 'MigrationRunner'] as $class) {
    $file = dirname(__DIR__) . '/app/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
}
require_once dirname(__DIR__) . '/app/Controllers/NewbotController.php';

use NewDoc\Controllers\NewbotController;

$assert = static function (bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException('Falha criação Newbot com filhos: ' . $label);
    }
};
$at = '2026-01-01 00:00:00';
$secret = 'Segredo-123';
$previousSession = $_SESSION ?? [];
$previousPost = $_POST ?? [];
$previousKey = getenv('CREDENTIALS_KEY');
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
    $controller = new NewbotController($pdo);
    $count = static fn (string $table): int => (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    $post = static fn (array $children): array => [
        'instance_name' => 'Nova', 'status' => 'inativo', 'source_document_id' => '',
        'notes' => '', 'instance_code' => '', 'access_link' => '', 'children' => $children,
    ];

    $_POST = $post([
        'channels' => [
            ['channel_name' => 'Canal 1', 'plan' => 'QR', 'quantity' => '3', 'status' => 'ativo'],
            ['channel_name' => '', 'plan' => ''],
            ['channel_name' => 'Canal 2', 'quantity' => '1'],
        ],
        'integrations' => [
            ['name' => 'Integracao A', 'username' => 'usuario', 'email' => 'a@example.invalid', 'password' => $secret, 'status' => 'ativo'],
        ],
    ]);
    $assert($controller->store(1)->status === 303, 'criação com filhos redireciona');
    $assert($count('newbot_instances') === 1, 'uma instância criada');
    $assert($count('newbot_channels') === 2, 'dois canais gravados e linha vazia ignorada');
    $assert($count('newbot_integrations') === 1, 'uma integração gravada');
    $instanceId = (int) $pdo->query('SELECT id FROM newbot_instances')->fetchColumn();
    $assert((int) $pdo->query('SELECT COUNT(*) FROM newbot_channels WHERE newbot_instance_id = ' . $instanceId)->fetchColumn() === 2, 'canais vinculados à instância criada');
    $stored = $pdo->query('SELECT password_encrypted FROM newbot_integrations')->fetchColumn();
    $assert(is_string($stored) && $stored !== $secret && NewDoc\CredentialCipher::decrypt($stored) === $secret, 'senha criptografada e recuperável');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'criacao' AND table_name = 'newbot_channels'")->fetchColumn() === 2, 'auditoria dos canais');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'criacao' AND table_name = 'newbot_integrations' AND field_name = 'record'")->fetchColumn() === 1, 'auditoria da integração');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE table_name = 'newbot_integrations' AND sensitive_field = 1")->fetchColumn() === 1, 'auditoria sensível da senha da integração');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE COALESCE(old_value, '') LIKE '%Segredo-123%' OR COALESCE(new_value, '') LIKE '%Segredo-123%'")->fetchColumn() === 0, 'senha fora da auditoria');
    $assert(!isset($_POST['children']) && ($_POST['instance_name'] ?? '') === 'Nova', 'POST restaurado sem children');

    $before = [$count('newbot_instances'), $count('newbot_channels'), $count('newbot_authentications'), $count('audit_logs')];
    $_POST = $post([
        'channels' => [['channel_name' => 'Valido']],
        'authentications' => [['platform' => 'inexistente', 'status' => 'ativo']],
    ]);
    $assert($controller->store(1)->status === 422, 'filho inválido bloqueia a criação');
    $assert($before === [$count('newbot_instances'), $count('newbot_channels'), $count('newbot_authentications'), $count('audit_logs')], 'nada gravado com filho inválido');

    $_POST = $post(['channels' => array_fill(0, 51, ['channel_name' => 'X'])]);
    $assert($controller->store(1)->status === 422, 'limite de 50 registros por seção');
    $assert($before === [$count('newbot_instances'), $count('newbot_channels'), $count('newbot_authentications'), $count('audit_logs')], 'nada gravado acima do limite');

    $pdo->exec("CREATE TRIGGER falha_integracao BEFORE INSERT ON newbot_integrations WHEN NEW.name = 'gatilho' BEGIN SELECT RAISE(ABORT, 'falha simulada'); END");
    $before = [$count('newbot_instances'), $count('newbot_channels'), $count('newbot_integrations'), $count('audit_logs')];
    $_POST = $post([
        'channels' => [['channel_name' => 'Canal que deve sumir']],
        'integrations' => [['name' => 'gatilho', 'password' => $secret]],
    ]);
    $thrown = false;
    try {
        $controller->store(1);
    } catch (PDOException) {
        $thrown = true;
    }
    $assert($thrown, 'falha no INSERT do filho propaga a exceção');
    $assert(!$pdo->inTransaction(), 'transação encerrada após a falha');
    $assert($before === [$count('newbot_instances'), $count('newbot_channels'), $count('newbot_integrations'), $count('audit_logs')], 'rollback desfaz instância, canal e auditoria');

    echo "OK: criação da instância Newbot com filhos, transação única, rollback, bloqueio por filho inválido, limite por seção, senha criptografada e auditoria sem senha.\n";
} finally {
    $_SESSION = $previousSession;
    $_POST = $previousPost;
    if ($previousKey === false) {
        putenv('CREDENTIALS_KEY');
    } else {
        putenv('CREDENTIALS_KEY=' . $previousKey);
    }
}