<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

use NewDoc\NewsicPdfReport;

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'newdoc-pdf-samples-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('Falha ao criar pasta temporária de amostras.');
}
$company = ['id' => 1, 'name' => 'EMPRESA FICTÍCIA - Laboratório de documentação técnica'];
$instance = [
    'id' => 1, 'server_name' => 'AMOSTRA FICTÍCIA - não representa configuração real',
    'server_type' => 'cloud', 'status' => 'ativo', 'software_version' => 'TESTE',
    'internal_ip' => '192.0.2.10', 'external_ip' => '198.51.100.10',
    'web_port' => 80, 'web_username' => 'usuario-ficticio', 'ssh_port' => 22,
    'ssh_username' => 'tecnico-ficticio', 'has_web_password' => true, 'has_ssh_password' => true,
    'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-02 11:00:00',
    'notes' => 'Somente dados fictícios. Conferir títulos, tabelas, observações e rodapés.',
];
$sections = ['ramais' => [], 'troncos' => [], 'ura' => [], 'roteamentos' => [], 'redes' => []];
$long = str_repeat('Texto fictício com acentuação, aspas "teste" e sinais < > &. ', 7);
$token = str_repeat('AbC123', 20);
$at = new DateTimeImmutable('2026-11-24 10:00:00');
$report = new NewsicPdfReport();
$save = static function (string $name, array $data) use ($directory, $company, $instance, $at, $report): void {
    $pdf = $report->render($company, $instance, $data, $at);
    if (!str_starts_with($pdf, '%PDF-')) {
        throw new RuntimeException('Conteúdo PDF inválido: ' . $name);
    }
    $path = $directory . DIRECTORY_SEPARATOR . $name . '.pdf';
    $handle = fopen($path, 'xb');
    if ($handle === false) throw new RuntimeException('Falha ao criar amostra: ' . $name);
    try {
        $offset = 0;
        $length = strlen($pdf);
        while ($offset < $length) {
            $written = fwrite($handle, substr($pdf, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('Falha ao gravar amostra: ' . $name);
            $offset += $written;
        }
    } finally {
        fclose($handle);
    }
    clearstatcache(true, $path);
    if (filesize($path) !== strlen($pdf)) throw new RuntimeException('Amostra incompleta: ' . $name);
    fwrite(STDOUT, $path . PHP_EOL);
};
$extension = static fn (int $n): array => [
    'id' => $n, 'extension_number' => (string) (2000 + $n), 'has_registration_password' => true,
    'ip_address' => '192.0.2.' . (1 + $n % 200), 'user_name' => 'Usuário fictício ' . $n,
    'department' => $n % 10 === 0 ? $token : 'Suporte técnico', 'call_recording' => 1,
    'permission_lof' => 1, 'permission_loc' => 1, 'permission_ddf' => 1,
    'permission_ddc' => 0, 'permission_ddi' => 0, 'permission_srv' => 1, 'status' => 'ativo',
    'notes' => $n % 10 === 0 ? $long : 'Observação fictícia ' . $n,
];
$small = $sections;
$small['ramais'][] = $extension(1);
$small['troncos'][] = ['pilot_number' => 'PILOTO-FICTICIO', 'trunk_type' => 'sip_trunk', 'carrier' => 'Operadora fictícia', 'status' => 'ativo', 'notes' => 'Observação fictícia'];
$small['ura'][] = ['ura_type' => 'Principal', 'option' => '1', 'schedule' => 'Comercial', 'recording' => 'Áudio fictício', 'destination' => 'Suporte', 'notes' => 'Observação fictícia'];
$save('01-secoes-vazias', $small);
for ($n = 1; $n <= 140; $n++) $sections['ramais'][] = $extension($n);
for ($n = 1; $n <= 12; $n++) {
    $sections['troncos'][] = ['pilot_number' => 'PILOTO-' . $n, 'trunk_type' => 'sip_trunk', 'carrier' => 'Operadora fictícia ' . $n, 'receives_calls' => 1, 'channel_quantity' => 20, 'ddrs' => $token, 'status' => 'ativo', 'notes' => $long];
    $sections['ura'][] = ['ura_type' => 'Principal', 'option' => (string) $n, 'schedule' => 'Segunda a sexta - horário fictício', 'recording' => 'Áudio fictício ' . $n, 'destination' => $token, 'notes' => $long];
    $sections['roteamentos'][] = ['ipo' => 'IPO-' . $n, 'number' => 'NUMERO-' . $n, 'name' => 'Destino fictício ' . $n, 'forwarding' => $token, 'recording' => 'Áudio fictício', 'destination' => 'Setor fictício', 'notes' => $long];
}
for ($n = 1; $n <= 6; $n++) {
    $sections['redes'][] = ['id' => $n, 'gateway' => '192.0.2.1', 'network_type' => 'LAN fictícia', 'interface_ip' => '192.0.2.10', 'subnet_mask' => '255.255.255.0', 'dns' => '192.0.2.53', 'proxy_ip' => '198.51.100.20', 'proxy_port' => 5060, 'proxy_type' => 'Proxy fictício', 'audio_ip' => '192.0.2.30', 'audio_subnet_mask' => '255.255.255.0', 'audio_gateway' => '192.0.2.1', 'notes' => $long . "\n" . $token];
}
$save('02-volume-e-textos-longos', $sections);
fwrite(STDOUT, 'Amostras geradas. A inspeção visual continua obrigatória; este script não certifica ausência de cortes.' . PHP_EOL);
