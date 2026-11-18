<?php

declare(strict_types=1);

define('NEWDOC_ROOT', dirname(__DIR__));
require NEWDOC_ROOT . '/bootstrap.php';

function optionValue(array $arguments, string $name): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($arguments as $argument) {
        if (str_starts_with($argument, $prefix)) {
            return substr($argument, strlen($prefix));
        }
    }

    return null;
}

$name = trim((string) optionValue($argv, 'name'));
$email = strtolower(trim((string) optionValue($argv, 'email')));
$password = getenv('NEWDOC_ADMIN_PASSWORD');
$password = $password === false ? '' : $password;

if (strlen($name) < 2 || strlen($name) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php bin/create-admin.php --name=\"Nome\" --email=\"admin@empresa.com\"\n");
    exit(2);
}
if (NewDoc\PasswordPolicy::isValid($password) === false) {
    fwrite(STDERR, "A senha deve ter entre 6 e 12 caracteres. Não use espaços no início ou no fim.\n");
    exit(2);
}

try {
    $pdo = NewDoc\Database::connection();
    NewDoc\MigrationRunner::migrate($pdo);
    $existing = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ((int) $existing > 0) {
        fwrite(STDERR, "Já existe um administrador cadastrado. Nenhum usuário foi criado.\n");
        exit(3);
    }

    $now = date('Y-m-d H:i:s');
    $statement = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, active, created_at, updated_at)
         VALUES (:name, :email, :password_hash, \'admin\', 1, :created_at, :updated_at)'
    );
    $statement->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    NewDoc\Audit::record($pdo, 'criacao', 'users', (int) $pdo->lastInsertId(), 'administrator');
    fwrite(STDOUT, "Administrador criado. A senha não foi exibida nem registrada.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha ao criar o administrador: ' . get_class($exception) . PHP_EOL);
    exit(1);
} finally {
    if (function_exists('sodium_memzero')) {
        sodium_memzero($password);
    }
}
