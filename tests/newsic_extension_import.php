<?php

declare(strict_types=1);

foreach (['Config', 'Auth', 'Audit', 'CredentialCipher', 'NewsicExtensionCsv', 'NewsicExtensionImport'] as $class) {
    require_once dirname(__DIR__) . '/app/' . $class . '.php';
}

use NewDoc\CredentialCipher;
use NewDoc\NewsicExtensionImport;

$assert = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('Falha importação: ' . $label);
};
$reject = static function (callable $action) use ($assert): void {
    $failed = false;
    try { $action(); } catch (RuntimeException) { $failed = true; }
    $assert($failed, 'operação inválida recusada');
};
$previousKey = getenv('CREDENTIALS_KEY');
$previousSession = $_SESSION ?? [];
try {
    putenv('CREDENTIALS_KEY=' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    $_SESSION = ['user' => ['id' => 1, 'role' => 'technician']];
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE companies (id INTEGER PRIMARY KEY); CREATE TABLE documents (id INTEGER PRIMARY KEY); CREATE TABLE users (id INTEGER PRIMARY KEY); INSERT INTO companies VALUES (1); INSERT INTO users VALUES (1)');
    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/migrations/002_newsic_schema.sql'));
    $pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY, user_id INTEGER, action TEXT, table_name TEXT, record_id INTEGER, field_name TEXT, sensitive_field INTEGER, company_id INTEGER, ip_address TEXT, user_agent TEXT, created_at TEXT)');
    $pdo->exec("INSERT INTO newsic_instances (id, company_id, server_name, status, created_at, updated_at) VALUES (1, 1, 'Teste A', 'ativo', '2026-01-01', '2026-01-01'), (2, 1, 'Teste B', 'inativo', '2026-01-01', '2026-01-01')");
    $service = new NewsicExtensionImport($pdo);
    $draft = $service->preview(1, "Ramal;Senha;LOF\n0020;  ficticia  ;Sim\n0021;;Não\n");
    $assert(count($draft['rows']) === 2, 'prévia');
    $assert(!array_key_exists('registration_password', $draft['rows'][0]), 'prévia sem senha');
    $assert(!str_contains($draft['sealed'], 'ficticia'), 'conteúdo da prévia criptografado');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM newsic_extensions')->fetchColumn() === 0, 'prévia sem gravação');
    $assert($service->commit(1, $draft['sealed']) === 2, 'importação');
    $payload = $pdo->query("SELECT registration_password_encrypted FROM newsic_extensions WHERE extension_number = '0020'")->fetchColumn();
    $assert(CredentialCipher::decrypt((string) $payload) === '  ficticia  ', 'senha preservada');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM audit_logs WHERE sensitive_field = 1')->fetchColumn() === 1, 'auditoria sensível');
    $reject(static fn () => $service->commit(1, $draft['sealed']));
    $other = $service->preview(2, "Ramal;Senha\n0020;outra\n");
    $reject(static fn () => $service->commit(1, $other['sealed']));
    $_SESSION['user']['id'] = 2;
    $reject(static fn () => $service->commit(2, $other['sealed']));
    $_SESSION['user']['id'] = 1;
    $_SESSION['user']['role'] = 'viewer';
    $reject(static fn () => $service->preview(2, "Ramal\n1234\n"));
    $_SESSION['user']['role'] = 'technician';
    $reject(static fn () => $service->commit(2, 'invalido'));
    $assert($service->commit(2, $other['sealed']) === 1, 'mesmo número em outro servidor');
    $rollback = $service->preview(2, "Ramal\n3000\n3001\n");
    $pdo->exec("CREATE TRIGGER fail_second BEFORE INSERT ON newsic_extensions WHEN NEW.extension_number = '3001' BEGIN SELECT RAISE(ABORT, 'teste'); END");
    $beforeAudit = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    $reject(static fn () => $service->commit(2, $rollback['sealed']));
    $assert((int) $pdo->query("SELECT COUNT(*) FROM newsic_extensions WHERE extension_number IN ('3000', '3001')")->fetchColumn() === 0, 'rollback integral');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $beforeAudit, 'rollback da auditoria');
    echo "OK: prévia protegida, importação transacional, autorização, duplicidade e rollback.\n";
} finally {
    if ($previousKey === false) putenv('CREDENTIALS_KEY');
    else putenv('CREDENTIALS_KEY=' . $previousKey);
    $_SESSION = $previousSession;
}
