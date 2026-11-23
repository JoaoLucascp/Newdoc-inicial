<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use NewDoc\CredentialCipher;
use NewDoc\NewsicExport;

$assert = static function (bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('Falha exportação: ' . $label);
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
    $pdo->exec("INSERT INTO users (id,name,email,password_hash,role,created_at,updated_at) VALUES (1,'Teste','teste@example.invalid','ficticio','technician','2026-01-01','2026-01-01'); INSERT INTO companies (id,name,created_at,updated_at) VALUES (1,'Empresa fictícia','2026-01-01','2026-01-01'); INSERT INTO newsic_instances (id,company_id,server_name,status,created_at,updated_at) VALUES (1,1,'Servidor fictício A','ativo','2026-01-01','2026-01-01'),(2,1,'Servidor exclusivo B','inativo','2026-01-01','2026-01-01')");
    $secret = '  senha-ficticia-á  ';
    $encrypted = CredentialCipher::encrypt($secret);
    $query = $pdo->prepare('UPDATE newsic_instances SET web_password_encrypted = ?, ssh_password_encrypted = ? WHERE id = 1');
    $query->execute([$encrypted, $encrypted]);
    $query = $pdo->prepare("INSERT INTO newsic_extensions (newsic_instance_id,extension_number,registration_password_encrypted,notes,created_at,updated_at) VALUES (1,'2001',?,?,'2026-01-01','2026-01-01')");
    $query->execute([$encrypted, '=1+1']);
    $pdo->exec("INSERT INTO newsic_extensions (newsic_instance_id,extension_number,created_at,updated_at) VALUES (2,'EXCLUSIVO-B','2026-01-01','2026-01-01'); INSERT INTO newsic_trunks (newsic_instance_id,pilot_number,created_at,updated_at) VALUES (1,'TRONCO-FICTICIO','2026-01-01','2026-01-01'); INSERT INTO newsic_ura_options (newsic_instance_id,option,created_at,updated_at) VALUES (1,'URA-FICTICIA','2026-01-01','2026-01-01'); INSERT INTO newsic_routings (newsic_instance_id,name,created_at,updated_at) VALUES (1,'ROTA-FICTICIA','2026-01-01','2026-01-01'); INSERT INTO newsic_networks (newsic_instance_id,gateway,created_at,updated_at) VALUES (1,'192.0.2.1','2026-01-01','2026-01-01')");
    $service = new NewsicExport($pdo);
    $reject(static fn () => $service->generate(1, 'csv'));
    $reject(static fn () => $service->generate(1, 'exe', true));
    $reject(static fn () => $service->generate(999, 'csv', true));
    $_SESSION['user']['role'] = 'viewer';
    $reject(static fn () => $service->generate(1, 'csv', true));
    $_SESSION['user']['role'] = 'technician';
    $pdo->exec('UPDATE users SET active = 0 WHERE id = 1');
    $reject(static fn () => $service->generate(1, 'csv', true));
    $pdo->exec('UPDATE users SET active = 1 WHERE id = 1');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 0, 'recusas sem geração auditada');
    $csv = $service->generate(1, 'csv', true);
    $assert($csv['content_type'] === 'text/csv; charset=UTF-8', 'tipo CSV');
    $assert(str_starts_with($csv['content'], "\xEF\xBB\xBF"), 'BOM UTF-8');
    $assert(str_contains($csv['content'], $secret), 'espaços e acentos de senha preservados');
    $assert(!str_contains($csv['content'], $encrypted) && !str_contains($csv['content'], '_encrypted'), 'sem ciphertext');
    $assert(!str_contains($csv['content'], 'EXCLUSIVO-B') && !str_contains($csv['content'], 'Servidor exclusivo B'), 'isolamento por servidor');
    $assert(str_contains($csv['content'], "'=1+1"), 'proteção de fórmula');
    foreach (['TRONCO-FICTICIO', 'URA-FICTICIA', 'ROTA-FICTICIA', '192.0.2.1'] as $value) $assert(str_contains($csv['content'], $value), 'seções completas');
    $pdf = $service->generate(1, 'pdf', true);
    $assert($pdf['content_type'] === 'application/pdf' && str_starts_with($pdf['content'], '%PDF-'), 'PDF renderizado');
    $assert(strlen($pdf['content']) > 1000, 'PDF não vazio');
    $audit = $pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $assert(count($audit) === 2, 'auditoria por geração');
    foreach ($audit as $row) {
        $assert($row['action'] === 'consulta' && (int) $row['record_id'] === 1 && (int) $row['company_id'] === 1, 'contexto auditado');
        $assert((int) $row['sensitive_field'] === 1 && $row['old_value'] === null && $row['new_value'] === null, 'auditoria sem credenciais');
    }
    $assert($audit[0]['field_name'] === 'exportacao_csv' && $audit[1]['field_name'] === 'exportacao_pdf', 'formatos auditados');
    $pdo->exec("CREATE TRIGGER fail_export_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT,'teste'); END");
    $reject(static fn () => $service->generate(1, 'csv', true));
    $assert(!$pdo->inTransaction(), 'transação encerrada após falha');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 2, 'falha de auditoria bloqueia entrega');
    echo "OK: exportação CSV/PDF, confirmação, autorização, isolamento, proteção CSV e auditoria sem senhas.\n";
} finally {
    if ($previousKey === false) putenv('CREDENTIALS_KEY');
    else putenv('CREDENTIALS_KEY=' . $previousKey);
    $_SESSION = $previousSession;
}
