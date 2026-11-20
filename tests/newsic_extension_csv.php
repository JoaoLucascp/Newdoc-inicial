<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/NewsicExtensionCsv.php';

use NewDoc\NewsicExtensionCsv;

$assert = static function (bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('Falha CSV: ' . $label);
};
$reject = static function (string $csv) use ($assert): void {
    $failed = false;
    try {
        NewsicExtensionCsv::parse($csv);
    } catch (InvalidArgumentException $exception) {
        $failed = true;
        $assert(!str_contains($exception->getMessage(), 'segredo-ficticio'), 'erro sem conteúdo sensível');
    }
    $assert($failed, 'entrada inválida rejeitada');
};
$rows = NewsicExtensionCsv::parse("\xEF\xBB\xBFRamal;Senha;IP;LOF;Status\n0020;  segredo-ficticio  ;192.0.2.1;Sim;Manutenção\n");
$assert(count($rows) === 1, 'quantidade');
$assert($rows[0]['extension_number'] === '0020', 'zeros iniciais');
$assert($rows[0]['registration_password'] === '  segredo-ficticio  ', 'espaços da senha');
$assert($rows[0]['permission_lof'] === 1 && $rows[0]['permission_loc'] === 0, 'permissões');
$assert($rows[0]['status'] === 'manutencao', 'status acentuado');
$rows = NewsicExtensionCsv::parse("Ramal,Senha\n2001,\"valor,com;separadores\"\n");
$assert($rows[0]['registration_password'] === 'valor,com;separadores', 'aspas e delimitadores');
$rows = NewsicExtensionCsv::parse("Ramal;Senha\n2001;\"duas\nlinhas\"\n");
$assert($rows[0]['registration_password'] === "duas\nlinhas", 'senha multilinha');
$reject("Ramal;Senha\n2001;segredo-ficticio\n2001;outra\n");
$reject("Ramal;IP\n2001;ip-invalido\n");
$reject("Ramal;LOF\n2001;talvez\n");
$reject("Ramal;Status\n2001;desconhecido\n");
$reject("Ramal;Ramal\n2001;2002\n");
$reject("Ramal;Senha\n2001\n");
$reject("Ramal;Senha\n");
$reject("Ramal;Senha\n;segredo-ficticio\n");
$reject("Ramal;Senha\n2001;\0\n");
$reject("Ramal\n" . implode("\n", range(1, 1001)) . "\n");
$reject(str_repeat('x', 2097153));
echo "OK: CSV de ramais, preservação de senha e rejeição de entradas inválidas.\n";
