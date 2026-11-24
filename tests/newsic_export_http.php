<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use NewDoc\Application;
use NewDoc\Csrf;
use NewDoc\NewsicExport;
use NewDoc\Response;

// Exercises request dispatch without a network server or the application's database.
$assert = static function (bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('Falha rota de exportação Newsic: ' . $label);
};
$sessionBefore = $_SESSION ?? [];
$postBefore = $_POST;
$serverBefore = $_SERVER;
$pdo = null;
try {
    $_SESSION = ['user' => ['id' => 1, 'name' => 'Técnico fictício', 'role' => 'technician']];
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    foreach (['001_initial_schema.sql', '002_newsic_schema.sql'] as $migration) {
        $sql = file_get_contents(dirname(__DIR__) . '/database/migrations/' . $migration);
        if ($sql === false) throw new RuntimeException('Migration de teste indisponível.');
        $pdo->exec($sql);
    }
    $pdo->exec("INSERT INTO users (id,name,email,password_hash,role,created_at,updated_at) VALUES (1,'Técnico fictício','rota@example.invalid','hash-ficticio','technician','2026-01-01','2026-01-01'); INSERT INTO companies (id,name,created_at,updated_at) VALUES (1,'Empresa fictícia','2026-01-01','2026-01-01'); INSERT INTO newsic_instances (id,company_id,server_name,status,created_at,updated_at) VALUES (1,1,'Servidor fictício','ativo','2026-01-01','2026-01-01')");
    $app = new Application($pdo);
    $request = static function (string $method, array $post = [], int $id = 1) use ($app): Response {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = '/newsic/' . $id . '/exportar';
        $_POST = $post;
        return $app->handle();
    };
    $count = static fn (): int => (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    $token = Csrf::token();
    $get = $request('GET');
    $assert($get->status === 200, 'formulário autorizado');
    $assert(str_contains($get->body, 'name="_csrf"') && str_contains($get->body, 'name="confirm_secrets"'), 'formulários mantêm proteções');
    $assert(($get->headers['Cache-Control'] ?? '') === 'no-store, private', 'formulário sem cache');
    $assert($count() === 0, 'abrir opções não registra geração');

    unset($_SESSION['user']);
    $anonymous = $request('GET');
    $assert($anonymous->status === 302 && ($anonymous->headers['Location'] ?? '') === '/login', 'anônimo redirecionado para login');
    $_SESSION['user'] = ['id' => 1, 'name' => 'Leitor fictício', 'role' => 'viewer'];
    $assert($request('GET')->status === 403, 'leitor sem acesso ao formulário');
    $assert($request('POST', ['_csrf' => $token, 'format' => 'pdf'])->status === 403, 'leitor sem download');
    $_SESSION['user']['role'] = 'technician';
    $pdo->exec('UPDATE users SET active = 0 WHERE id = 1');
    $assert($request('GET')->status === 403, 'usuário inativo bloqueado pelo banco');
    $assert($request('POST', ['_csrf' => $token, 'format' => 'pdf'])->status === 403, 'usuário inativo sem download');
    $pdo->exec('UPDATE users SET active = 1 WHERE id = 1');
    $assert($request('POST', ['format' => 'pdf'])->status === 419, 'CSRF ausente');
    $assert($request('POST', ['_csrf' => 'invalido', 'format' => 'pdf'])->status === 419, 'CSRF inválido');
    $assert($request('POST', ['_csrf' => $token, 'format' => 'exe'])->status === 422, 'formato não permitido');
    $assert($request('POST', ['_csrf' => $token, 'format' => 'csv'])->status === 422, 'CSV sem autorização explícita');
    $assert($request('GET', [], 999)->status === 404, 'servidor inexistente');
    $unsupported = $request('PUT');
    $assert($unsupported->status === 405 && ($unsupported->headers['Allow'] ?? '') === 'GET, POST', 'método não permitido');
    $assert($count() === 0, 'requisições recusadas não geram exportação auditada');

    $csv = $request('POST', ['_csrf' => $token, 'format' => 'csv', 'confirm_secrets' => '1']);
    $assert($csv->status === 200 && ($csv->headers['Content-Type'] ?? '') === 'text/csv; charset=UTF-8', 'download CSV autorizado');
    $assert(str_starts_with($csv->body, "\xEF\xBB\xBF"), 'CSV UTF-8 com BOM');
    $assert(str_contains($csv->headers['Content-Disposition'] ?? '', '.csv"'), 'nome do download CSV');
    $assert($count() === 1, 'uma auditoria por CSV');

    // Deliberately invalid ciphertext: a PDF must not attempt to decrypt it.
    $fakeCipher = 'credencial-ficticia-nao-decifravel';
    $update = $pdo->prepare('UPDATE newsic_instances SET web_password_encrypted = ?, ssh_password_encrypted = ? WHERE id = 1');
    $update->execute([$fakeCipher, $fakeCipher]);
    $insert = $pdo->prepare("INSERT INTO newsic_extensions (newsic_instance_id,extension_number,registration_password_encrypted,created_at,updated_at) VALUES (1,'2001',?,'2026-01-01','2026-01-01')");
    $insert->execute([$fakeCipher]);
    $redacted = NewsicExport::redact(['id' => 1, 'web_password_encrypted' => $fakeCipher]);
    $assert(!array_key_exists('web_password_encrypted', $redacted) && $redacted['has_web_password'] === true, 'redação remove credencial antes do layout');
    $pdf = $request('POST', ['_csrf' => $token, 'format' => 'pdf']);
    $assert($pdf->status === 200 && ($pdf->headers['Content-Type'] ?? '') === 'application/pdf', 'PDF dispensa confirmação de senhas legíveis');
    $assert(str_starts_with($pdf->body, '%PDF-') && strlen($pdf->body) > 1000, 'PDF renderizado sem decifrar credenciais');
    $assert(str_contains($pdf->headers['Content-Disposition'] ?? '', '.pdf"'), 'nome do download PDF');
    $assert(($pdf->headers['Cache-Control'] ?? '') === 'no-store, private' && ($pdf->headers['X-Content-Type-Options'] ?? '') === 'nosniff', 'download protegido contra cache e detecção de tipo');
    $assert($count() === 2, 'uma auditoria por PDF');
    $audit = $pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $assert($audit[0]['field_name'] === 'exportacao_csv' && $audit[1]['field_name'] === 'exportacao_pdf', 'formatos auditados');
    foreach ($audit as $row) {
        $assert($row['action'] === 'consulta' && (int) $row['record_id'] === 1 && (int) $row['company_id'] === 1 && (int) $row['user_id'] === 1, 'autor e servidor auditados');
        $assert($row['old_value'] === null && $row['new_value'] === null && (int) $row['sensitive_field'] === 1, 'auditoria sem valores sensíveis');
    }
    $csvFailure = $request('POST', ['_csrf' => $token, 'format' => 'csv', 'confirm_secrets' => '1']);
    $assert($csvFailure->status === 500 && !isset($csvFailure->headers['Content-Disposition']), 'falha de decifragem impede download CSV');
    $assert(!str_contains($csvFailure->body, $fakeCipher) && $count() === 2, 'falha não expõe dados nem registra sucesso');
    $pdo->exec("CREATE TRIGGER block_export_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT,'falha ficticia'); END");
    $failure = $request('POST', ['_csrf' => $token, 'format' => 'pdf']);
    $assert($failure->status === 500 && !isset($failure->headers['Content-Disposition']) && !str_starts_with($failure->body, '%PDF-'), 'falha de auditoria impede entrega PDF');
    $assert(!$pdo->inTransaction() && $count() === 2, 'rollback após falha de auditoria');
    echo "OK: rotas Newsic, autorização, CSRF, confirmação CSV, PDF sem decifragem, downloads e auditoria.\n";
} finally {
    $_SESSION = $sessionBefore;
    $_POST = $postBefore;
    $_SERVER = $serverBefore;
    $pdo = null;
}
