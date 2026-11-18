<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'newdoc-test-' . bin2hex(random_bytes(6));
if (!mkdir($temporaryDirectory, 0700, true)) {
    throw new RuntimeException('Não foi possível criar o diretório temporário de testes.');
}
$databasePath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'test.sqlite';
putenv('DB_PATH=' . $databasePath);
putenv('CREDENTIALS_KEY=' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
NewDoc\Database::resetConnection();

try {
    $pdo = NewDoc\Database::connection();
    $migrations = NewDoc\MigrationRunner::migrate($pdo);
    $expectedMigrations = [
        '001_initial_schema.sql',
        '002_newsic_schema.sql',
        '003_newbot_schema.sql',
    ];

    expect(
        $migrations === $expectedMigrations,
        'As migrations iniciais, Newsic e Newbot deveriam ser aplicadas.'
    );
    expect(NewDoc\MigrationRunner::migrate($pdo) === [], 'Migrations aplicadas não devem ser repetidas.');

    $now = date('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        'INSERT INTO companies (name, contract_status, created_at, updated_at)
         VALUES (:name, :status, :created_at, :updated_at)'
    );
    foreach (['Empresa Homônima', 'Empresa Homônima'] as $name) {
        $insert->execute([
            'name' => $name,
            'status' => 'inexistente',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    expect((int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn() === 2, 'Nomes iguais devem ser permitidos.');

    $companyId = (int) $pdo->query('SELECT id FROM companies ORDER BY id LIMIT 1')->fetchColumn();
    $newsicInsert = $pdo->prepare(
        'INSERT INTO newsic_instances (company_id, server_name, status, created_at, updated_at)
         VALUES (:company_id, :server_name, :status, :created_at, :updated_at)'
    );
    $newsicInsert->execute([
        'company_id' => $companyId,
        'server_name' => 'IPBX primário',
        'status' => 'ativo',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $newsicId = (int) $pdo->lastInsertId();
    $duplicateActiveRejected = false;
    try {
        $newsicInsert->execute([
            'company_id' => $companyId,
            'server_name' => 'IPBX duplicado',
            'status' => 'ativo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (PDOException) {
        $duplicateActiveRejected = true;
    }
    expect($duplicateActiveRejected, 'Uma empresa não pode ter mais de uma instância Newsic ativa.');

    $newsicInsert->execute([
        'company_id' => $companyId,
        'server_name' => 'IPBX histórico',
        'status' => 'inativo',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $extensionInsert = $pdo->prepare(
        'INSERT INTO newsic_extensions (newsic_instance_id, extension_number, status, created_at, updated_at)
         VALUES (:instance_id, :extension_number, :status, :created_at, :updated_at)'
    );
    $extensionInsert->execute([
        'instance_id' => $newsicId,
        'extension_number' => '101',
        'status' => 'ativo',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $duplicateExtensionRejected = false;
    try {
        $extensionInsert->execute([
            'instance_id' => $newsicId,
            'extension_number' => '101',
            'status' => 'inativo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (PDOException) {
        $duplicateExtensionRejected = true;
    }
    expect($duplicateExtensionRejected, 'O número do ramal deve ser único por instância Newsic.');

    $password = 'Senha de teste segura 2026!';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    expect(password_verify($password, $hash), 'O hash de senha deve verificar a senha original.');
    expect($hash !== $password, 'Senha de usuário não pode ser armazenada em texto puro.');

    foreach ([
        'abc123',
        'Joao@2026',
        'New doc',
        'Ébc123',
        'A1b2C3d4E5f6',
        '_-+={}[]',
        ':;,.?/~',
    ] as $validPassword) {
        expect(NewDoc\PasswordPolicy::isValid($validPassword), 'A política deve aceitar senhas válidas de 6 a 12 caracteres.');
    }
    foreach (['', 'abc12', 'abc1234567890', ' abc123', 'abc123 ', 'abc`123', "abc\t12"] as $invalidPassword) {
        expect(!NewDoc\PasswordPolicy::isValid($invalidPassword), 'A política deve rejeitar senhas fora dos limites ou caracteres permitidos.');
    }

    $secret = '  credencial de teste: á@  ';
    $encrypted = NewDoc\CredentialCipher::encrypt($secret);
    expect(!str_contains($encrypted, $secret), 'O valor sensível não pode aparecer no texto criptografado.');
    expect(NewDoc\CredentialCipher::decrypt($encrypted) === $secret, 'A credencial deve ser recuperável com a chave correta.');
    $storeCredential = $pdo->prepare(
        'UPDATE newsic_instances SET web_password_encrypted = :credential WHERE id = :id'
    );
    $storeCredential->execute(['credential' => $encrypted, 'id' => $newsicId]);
    $storedCredential = $pdo->prepare(
        'SELECT web_password_encrypted FROM newsic_instances WHERE id = :id'
    );
    $storedCredential->execute(['id' => $newsicId]);
    $storedValue = $storedCredential->fetchColumn();
    expect(is_string($storedValue) && $storedValue !== $secret, 'Credenciais Newsic não podem ser armazenadas em texto puro.');
    expect(NewDoc\CredentialCipher::decrypt($storedValue) === $secret, 'A credencial Newsic deve manter exatamente os caracteres originais.');

    NewDoc\Audit::record($pdo, 'alteracao', 'newsic_instances', 4, 'web_password', null, true);
    $audit = $pdo->query('SELECT old_value, new_value, sensitive_field FROM audit_logs LIMIT 1')->fetch();
    expect($audit['old_value'] === null && $audit['new_value'] === null, 'Auditoria sensível deve omitir valores.');
    expect((int) $audit['sensitive_field'] === 1, 'Auditoria deve marcar o campo como sensível.');

    NewDoc\Session::start();
    $token = NewDoc\Csrf::token();
    expect(NewDoc\Csrf::verify($token), 'O token CSRF gerado deve ser aceito.');
    expect(!NewDoc\Csrf::verify('token-invalido'), 'Um token CSRF inválido deve ser recusado.');
    NewDoc\Session::destroy();

    $pdo = null;
    NewDoc\Database::resetConnection();
    fwrite(STDOUT, "OK: migrations, Newsic, homônimos, hash de senha, criptografia, auditoria sensível e CSRF.\n");
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        NewDoc\Session::destroy();
    }
    unset(
        $storedCredential,
        $storeCredential,
        $extensionInsert,
        $newsicInsert,
        $insert,
        $pdo
    );
    NewDoc\Database::resetConnection();
    gc_collect_cycles();
    foreach ([$databasePath, $databasePath . '-wal', $databasePath . '-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (is_dir($temporaryDirectory)) {
        rmdir($temporaryDirectory);
    }
}
