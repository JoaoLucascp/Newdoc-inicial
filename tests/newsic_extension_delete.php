<?php

declare(strict_types=1);

foreach (['Config', 'Auth', 'Audit', 'CredentialCipher', 'NewsicExtensionDelete'] as $class) {
    require_once dirname(__DIR__) . '/app/' . $class . '.php';
}

use NewDoc\CredentialCipher;
use NewDoc\NewsicExtensionDelete;

$assert = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('Falha exclusão: ' . $label);
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
    foreach (['001_initial_schema.sql', '002_newsic_schema.sql'] as $migration) {
        $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/migrations/' . $migration));
    }
    $pdo->exec("INSERT INTO users (id, name, email, password_hash, role, created_at, updated_at) VALUES (1, 'Teste', 'teste@example.invalid', 'hash-ficticio', 'technician', '2026-01-01', '2026-01-01'); INSERT INTO companies (id, name, created_at, updated_at) VALUES (1, 'Empresa teste', '2026-01-01', '2026-01-01'); INSERT INTO newsic_instances (id, company_id, server_name, status, created_at, updated_at) VALUES (1, 1, 'Servidor A', 'ativo', '2026-01-01', '2026-01-01'), (2, 1, 'Servidor B', 'inativo', '2026-01-01', '2026-01-01')");
    $insert = $pdo->prepare("INSERT INTO newsic_extensions (id, newsic_instance_id, extension_number, registration_password_encrypted, created_at, updated_at) VALUES (?, ?, ?, ?, '2026-01-01', '2026-01-01')");
    foreach ([[1, 1, '2001'], [2, 1, '2002'], [3, 2, '2001']] as $row) {
        $insert->execute(array_merge($row, [CredentialCipher::encrypt('senha-ficticia')]));
    }
    $service = new NewsicExtensionDelete($pdo);
    $draft = $service->preview(1, ['1', '2']);
    $assert(count($draft['rows']) === 2, 'prévia');
    $assert(array_keys($draft['rows'][0]) === ['id', 'extension_number'], 'prévia sem credenciais');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM newsic_extensions')->fetchColumn() === 3, 'prévia não exclui');
    $reject(static fn () => $service->commit(1, $draft['sealed']));
    $reject(static fn () => $service->preview(1, [1, 3]));
    $reject(static fn () => $service->preview(1, []));
    $reject(static fn () => $service->preview(1, [1, 1]));
    $reject(static fn () => $service->preview(1, [true]));
    $reject(static fn () => $service->commit(2, $draft['sealed'], true));
    $_SESSION['user']['id'] = 2;
    $reject(static fn () => $service->commit(1, $draft['sealed'], true));
    $_SESSION['user'] = ['id' => 1, 'role' => 'viewer'];
    $reject(static fn () => $service->preview(1, [1]));
    $_SESSION['user']['role'] = 'technician';
    $expired = json_decode(CredentialCipher::decrypt($draft['sealed']), true, 16, JSON_THROW_ON_ERROR);
    $expired['expires'] = time() - 1;
    $expiredSealed = CredentialCipher::encrypt(json_encode($expired, JSON_THROW_ON_ERROR));
    $reject(static fn () => $service->commit(1, $expiredSealed, true));
    $pdo->exec("UPDATE newsic_extensions SET notes = 'alterado' WHERE id = 1");
    $reject(static fn () => $service->commit(1, $draft['sealed'], true));
    $assert((int) $pdo->query('SELECT COUNT(*) FROM newsic_extensions')->fetchColumn() === 3, 'alteração após prévia bloqueia exclusão');
    $draft = $service->preview(1, [1, 2]);
    $pdo->exec("CREATE TRIGGER fail_delete_audit BEFORE INSERT ON audit_logs WHEN NEW.action = 'exclusao' AND NEW.record_id = 2 BEGIN SELECT RAISE(ABORT, 'teste'); END");
    $reject(static fn () => $service->commit(1, $draft['sealed'], true));
    $assert((int) $pdo->query('SELECT COUNT(*) FROM newsic_extensions')->fetchColumn() === 3, 'rollback dos ramais');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 0, 'rollback da auditoria');
    $pdo->exec('DROP TRIGGER fail_delete_audit');
    $assert($service->commit(1, $draft['sealed'], true) === 2, 'exclusão confirmada');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM newsic_extensions WHERE id = 3')->fetchColumn() === 1, 'outro servidor preservado');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'exclusao' AND sensitive_field = 1 AND old_value IS NULL AND new_value IS NULL")->fetchColumn() === 2, 'auditoria sem valores');
    $reject(static fn () => $service->commit(1, $draft['sealed'], true));
    echo "OK: exclusão confirmada, isolamento por servidor, expiração, alteração de registros e rollback auditado.\n";
} finally {
    if ($previousKey === false) putenv('CREDENTIALS_KEY');
    else putenv('CREDENTIALS_KEY=' . $previousKey);
    $_SESSION = $previousSession;
}
