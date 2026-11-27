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
        throw new RuntimeException('Falha campos da instância Newbot: ' . $label);
    }
};
$at = '2026-01-01 00:00:00';
$previousSession = $_SESSION ?? [];
$previousPost = $_POST ?? [];
try {
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
    $pdo->exec("INSERT INTO newbot_channels (id, newbot_instance_id, channel_name, status, created_at, updated_at) VALUES (1, 1, 'Canal A', 'ativo', '$at', '$at')");
    $pdo->exec("INSERT INTO newbot_supervisors (id, newbot_instance_id, login, created_at, updated_at) VALUES (1, 1, 'sup-sem-nome', '$at', '$at')");
    $controller = new NewbotController($pdo);
    $post = static fn (array $extra): array => $extra + [
        'instance_name' => 'Nova', 'status' => 'inativo', 'source_document_id' => '',
        'notes' => '', 'instance_code' => '', 'access_link' => '',
    ];
    $instance = static fn (int $id): array => $pdo->query('SELECT instance_code, access_link FROM newbot_instances WHERE id = ' . $id)->fetch();

    $_POST = $post(['instance_code' => 'newtel-comercial', 'access_link' => 'https://newbot.example.invalid/painel']);
    $assert($controller->store(1)->status === 303, 'criação com ID e link');
    $created = $pdo->query("SELECT instance_code, access_link FROM newbot_instances WHERE instance_name = 'Nova'")->fetch();
    $assert($created['instance_code'] === 'newtel-comercial' && $created['access_link'] === 'https://newbot.example.invalid/painel', 'campos persistidos na criação');

    $before = (int) $pdo->query('SELECT COUNT(*) FROM newbot_instances')->fetchColumn();
    foreach (['javascript:alert(1)', 'ftp://host.invalid/x', 'https://a b.invalid', 'newbot.invalid'] as $bad) {
        $_POST = $post(['access_link' => $bad]);
        $assert($controller->store(1)->status === 422, 'link recusado: ' . $bad);
    }
    $_POST = $post(['instance_code' => str_repeat('a', 161)]);
    $assert($controller->store(1)->status === 422, 'ID acima do limite');
    $_POST = $post(['access_link' => 'https://a.invalid/' . str_repeat('a', 500)]);
    $assert($controller->store(1)->status === 422, 'link acima do limite');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM newbot_instances')->fetchColumn() === $before, 'nada gravado com entrada inválida');

    $_POST = $post(['instance_name' => 'A', 'status' => 'ativo', 'instance_code' => 'cod-a', 'access_link' => 'http://a.invalid']);
    $assert($controller->update(1)->status === 303, 'atualização');
    $row = $instance(1);
    $assert($row['instance_code'] === 'cod-a' && $row['access_link'] === 'http://a.invalid', 'campos persistidos na atualização');
    $fields = $pdo->query("SELECT field_name FROM audit_logs WHERE action = 'alteracao' AND table_name = 'newbot_instances' AND record_id = 1")->fetchAll(PDO::FETCH_COLUMN);
    $assert(in_array('instance_code', $fields, true) && in_array('access_link', $fields, true), 'auditoria dos campos novos');

    $_POST = $post(['instance_name' => 'A', 'status' => 'ativo', 'instance_code' => 'cod-a', 'access_link' => 'https://a.invalid/x"y']);
    $assert($controller->update(1)->status === 303, 'link com aspas aceito como texto');
    $html = $controller->show(1)->body;
    $assert(str_contains($html, 'cod-a'), 'ID exibido no detalhe');
    $assert(str_contains($html, 'a.invalid/x'), 'link exibido no detalhe');
    $assert(!str_contains($html, 'x"y'), 'aspas do link escapadas');
    $assert(str_contains($html, '/newbot/1/dados/excluir') && str_contains($html, 'Excluir registro') && str_contains($html, 'button-danger'), 'botão Excluir presente e em estilo de perigo');
    $assert(str_contains($html, 'sup-sem-nome') && !str_contains($html, 'Registro #'), 'rótulo do supervisor sem nome');

    $_SESSION['user']['role'] = 'viewer';
    $assert(!str_contains($controller->show(1)->body, '/dados/excluir'), 'viewer não vê o botão Excluir');
    $_SESSION['user']['role'] = 'technician';

    $_POST = $post(['instance_name' => 'A', 'status' => 'ativo']);
    $assert($controller->update(1)->status === 303, 'limpeza dos campos');
    $row = $instance(1);
    $assert($row['instance_code'] === null && $row['access_link'] === null, 'campos vazios viram NULL');

    echo "OK: ID e link de acesso da instância Newbot, validação http/https, auditoria, escape, rótulo de supervisor e botão Excluir.\n";
} finally {
    $_SESSION = $previousSession;
    $_POST = $previousPost;
}