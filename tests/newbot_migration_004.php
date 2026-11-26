<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/MigrationRunner.php';

use NewDoc\MigrationRunner;

$assert = static function (bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException('Falha migration 004: ' . $label);
    }
};
$rejected = static function (PDO $pdo, string $sql, array $params = []): bool {
    try {
        $pdo->prepare($sql)->execute($params);
    } catch (PDOException) {
        return true;
    }

    return false;
};
$columns = static fn (PDO $pdo, string $table): array => array_column(
    $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(),
    'name'
);
$directory = dirname(__DIR__) . '/database/migrations/';
$at = '2026-01-01 00:00:00';
$adminCreated = '2026-01-02 10:00:00';
$adminUpdated = '2026-01-03 11:00:00';

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('CREATE TABLE schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
foreach (['001_initial_schema.sql', '002_newsic_schema.sql', '003_newbot_schema.sql'] as $migration) {
    $pdo->exec((string) file_get_contents($directory . $migration));
    $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')->execute([$migration, $at]);
}

$pdo->exec("INSERT INTO companies (id, name, created_at, updated_at) VALUES (1, 'Empresa um', '$at', '$at'), (2, 'Empresa dois', '$at', '$at')");
$instance = $pdo->prepare('INSERT INTO newbot_instances (id, company_id, instance_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
foreach ([[1, 1, 'Newbot A', 'ativo'], [2, 1, 'Newbot B', 'inativo'], [3, 2, 'Newbot C', 'ativo']] as $row) {
    $instance->execute(array_merge($row, [$at, $at]));
}
$administrator = $pdo->prepare(
    'INSERT INTO newbot_administrators (id, newbot_instance_id, login, password_encrypted, supervisor_login, supervisor_email, notes, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$legacyAdministrators = [
    [1, 1, 'admin1', 'CIFRADO-ADM-1', 'sup1', 'sup1@example.invalid', 'nota do administrador'],
    [2, 1, 'admin2', 'CIFRADO-ADM-2', '', 'so-email@example.invalid', null],
    [3, 1, 'admin3', null, '   ', '  ', null],
    [4, 2, 'admin4', null, 'so-login', null, null],
    [5, 2, 'admin5', null, null, null, null],
    [6, 3, 'admin6', null, null, '  espacos@example.invalid  ', null],
];
foreach ($legacyAdministrators as $row) {
    $administrator->execute(array_merge($row, [$adminCreated, $adminUpdated]));
}
$pdo->exec("INSERT INTO newbot_channels (id, newbot_instance_id, channel_name, activation_date, plan, status, created_at, updated_at) VALUES (1, 1, 'WhatsApp', '2026-01-15', 'Plano A', 'ativo', '$at', '$at'), (2, 2, 'Facebook', NULL, NULL, 'inativo', '$at', '$at')");
$pdo->exec("INSERT INTO newbot_integrations (id, newbot_instance_id, name, username, password_encrypted, status, created_at, updated_at) VALUES (1, 1, 'API', 'usuario', 'CIFRADO-INT-1', 'ativo', '$at', '$at')");

$applied = MigrationRunner::migrate($pdo);
$assert($applied !== [] && $applied[0] === '004_newbot_management.sql', 'runner aplica a 004 sobre o legado');

$assert(array_diff(['instance_code', 'access_link'], $columns($pdo, 'newbot_instances')) === [], 'colunas da instância');
$assert(in_array('activation_time', $columns($pdo, 'newbot_channels'), true), 'hora de ativação do canal');
$assert(array_diff(['name', 'email'], $columns($pdo, 'newbot_administrators')) === [], 'nome e e-mail do administrador');
$assert(in_array('email', $columns($pdo, 'newbot_integrations'), true), 'e-mail da integração');
$assert(
    $columns($pdo, 'newbot_supervisors') === ['id', 'newbot_instance_id', 'name', 'login', 'email', 'password_encrypted', 'notes', 'created_at', 'updated_at'],
    'colunas dos supervisores'
);

$supervisors = $pdo->query(
    'SELECT newbot_instance_id, name, login, email, password_encrypted, notes, created_at, updated_at FROM newbot_supervisors ORDER BY id'
)->fetchAll();
$assert(
    array_map(static fn (array $r): array => [(int) $r['newbot_instance_id'], $r['login'], $r['email']], $supervisors) === [
        [1, 'sup1', 'sup1@example.invalid'],
        [1, null, 'so-email@example.invalid'],
        [2, 'so-login', null],
        [3, null, 'espacos@example.invalid'],
    ],
    'supervisores migrados, inclusive apenas e-mail ou apenas login'
);
$assert(
    $supervisors[1]['login'] === null && $supervisors[1]['email'] === 'so-email@example.invalid',
    'supervisor só com e-mail mantém login nulo, não vazio'
);
foreach ($supervisors as $supervisor) {
    $assert(
        $supervisor['name'] === null && $supervisor['password_encrypted'] === null && $supervisor['notes'] === null,
        'sem nome, senha ou observação inventados'
    );
    $assert(
        $supervisor['created_at'] === $adminCreated && $supervisor['updated_at'] === $adminUpdated,
        'datas herdadas do administrador'
    );
}

$admins = $pdo->query(
    'SELECT id, login, password_encrypted, supervisor_login, supervisor_email, name, email, notes FROM newbot_administrators ORDER BY id'
)->fetchAll();
$assert(count($admins) === 6, 'administradores preservados');
foreach ($legacyAdministrators as $index => $row) {
    $admin = $admins[$index];
    $assert(
        $admin['login'] === $row[2]
        && $admin['password_encrypted'] === $row[3]
        && $admin['supervisor_login'] === $row[4]
        && $admin['supervisor_email'] === $row[5]
        && $admin['notes'] === $row[6],
        'administrador legado intacto: ' . $row[2]
    );
    $assert($admin['name'] === null && $admin['email'] === null, 'nome e e-mail novos começam vazios: ' . $row[2]);
}

$channels = $pdo->query('SELECT id, activation_date, activation_time FROM newbot_channels ORDER BY id')->fetchAll();
$assert(
    $channels[0]['activation_date'] === '2026-01-15' && $channels[1]['activation_date'] === null,
    'datas dos canais preservadas'
);
$assert(
    $channels[0]['activation_time'] === null && $channels[1]['activation_time'] === null,
    'registros antigos sem hora inventada (nada de 00:00)'
);
$integration = $pdo->query('SELECT password_encrypted, email FROM newbot_integrations WHERE id = 1')->fetch();
$assert(
    $integration['password_encrypted'] === 'CIFRADO-INT-1' && $integration['email'] === null,
    'integração preservada'
);
$assert(
    (int) $pdo->query('SELECT COUNT(*) FROM newbot_instances WHERE instance_code IS NULL AND access_link IS NULL')->fetchColumn() === 3,
    'instâncias antigas sem ID textual nem link'
);
$assert($pdo->query('SELECT instance_name FROM newbot_instances WHERE id = 1')->fetchColumn() === 'Newbot A', 'nome da instância preservado');

foreach (['09:15', '23:59', '00:00'] as $valid) {
    $assert(
        !$rejected($pdo, 'UPDATE newbot_channels SET activation_time = ? WHERE id = 1', [$valid]),
        'hora válida aceita: ' . $valid
    );
}
$assert(!$rejected($pdo, 'UPDATE newbot_channels SET activation_time = NULL WHERE id = 1'), 'hora vazia (NULL) aceita');
foreach (['24:00', '29:59', '12:60', '9:15', '09:15:00', 'abc', ''] as $invalid) {
    $assert(
        $rejected($pdo, 'UPDATE newbot_channels SET activation_time = ? WHERE id = 1', [$invalid]),
        'hora inválida recusada: ' . $invalid
    );
}

$assert(
    !$rejected(
        $pdo,
        'UPDATE newbot_instances SET instance_code = ?, access_link = ? WHERE id IN (1, 2)',
        ['ID-1025/ñ', 'https://example.invalid/instancia/1025']
    ),
    'ID textual repetido é aceito (sem UNIQUE)'
);
$count = $pdo->prepare('SELECT COUNT(*) FROM newbot_instances WHERE instance_code = ?');
$count->execute(['ID-1025/ñ']);
$assert((int) $count->fetchColumn() === 2, 'ID textual gravado como texto livre');
$indexes = $pdo->query("PRAGMA index_list('newbot_instances')")->fetchAll();
$codeIndex = array_values(array_filter(
    $indexes,
    static fn (array $index): bool => $index['name'] === 'newbot_instances_instance_code_idx'
));
$assert(count($codeIndex) === 1 && (int) $codeIndex[0]['unique'] === 0, 'índice do ID textual existe e não é único');
foreach ($indexes as $index) {
    if ((int) $index['unique'] !== 1) {
        continue;
    }
    $info = array_column($pdo->query("PRAGMA index_info('" . $index['name'] . "')")->fetchAll(), 'name');
    $assert(!in_array('instance_code', $info, true), 'nenhum índice único cobre instance_code');
}

$assert(MigrationRunner::migrate($pdo) === [], 'migration não é reaplicada');
$assert((int) $pdo->query('SELECT COUNT(*) FROM newbot_supervisors')->fetchColumn() === 4, 'supervisores não duplicam em nova execução');

$pdo->exec('DELETE FROM newbot_instances WHERE id = 2');
$assert((int) $pdo->query('SELECT COUNT(*) FROM newbot_supervisors')->fetchColumn() === 3, 'cascade remove supervisores da instância excluída');
$assert(
    (int) $pdo->query('SELECT COUNT(*) FROM newbot_supervisors WHERE newbot_instance_id = 2')->fetchColumn() === 0,
    'nenhum supervisor órfão'
);
$assert(
    $rejected($pdo, 'INSERT INTO newbot_supervisors (newbot_instance_id, created_at, updated_at) VALUES (999, ?, ?)', [$at, $at]),
    'chave estrangeira recusa instância inexistente'
);

echo "OK: migration 004 do Newbot, ID textual sem unicidade, hora opcional sem preenchimento retroativo, supervisores migrados (inclusive só com e-mail), dados legados preservados, idempotência e cascade.\n";
