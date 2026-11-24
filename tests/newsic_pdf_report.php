<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use NewDoc\NewsicExport;
use NewDoc\NewsicPdfReport;

$assert = static function (bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('Falha PDF Newsic: ' . $label);
};
$company = ['id' => 1, 'name' => 'Empresa fictícia'];
$instance = [
    'id' => 1, 'company_id' => 1, 'server_name' => 'Servidor <b>fictício</b>', 'server_type' => 'cloud', 'status' => 'ativo',
    'internal_ip' => '192.0.2.10', 'external_ip' => '198.51.100.10', 'web_port' => 80, 'web_username' => 'admin',
    'ssh_port' => 22, 'ssh_username' => 'tecnico', 'has_web_password' => true, 'has_ssh_password' => false,
    'software_version' => '1.0', 'freemyip_link' => '', 'documentation_path' => null, 'source_document_id' => 1,
    'notes' => "linha 1\nlinha 2", 'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-02 11:00:00',
];
$extension = static fn (int $n): array => [
    'id' => $n, 'extension_number' => (string) (2000 + $n), 'has_registration_password' => true, 'ip_address' => '192.0.2.' . ($n % 200),
    'user_name' => 'usuario' . $n, 'department' => 'Suporte', 'call_recording' => 1, 'permission_lof' => 1, 'permission_loc' => 0,
    'permission_ddf' => 1, 'permission_ddc' => 0, 'permission_ddi' => 0, 'permission_srv' => 1, 'status' => 'ativo', 'notes' => 'Observação fictícia ' . $n,
];
$sections = [
    'ramais' => [$extension(1) + [], array_replace($extension(2), ['department' => str_repeat('X', 120)])],
    'troncos' => [], 'ura' => [], 'roteamentos' => [],
    'redes' => [['id' => 7, 'gateway' => '192.0.2.1', 'network_type' => 'LAN', 'dns' => '192.0.2.53', 'notes' => '']],
];
$report = new NewsicPdfReport();
$html = $report->html($company, $instance, $sections, new DateTimeImmutable('2026-11-24 10:00:00'));
foreach (['NEWSIC', 'NEWTEL', 'FICHA TÉCNICA DO SERVIDOR', 'DADOS DO SERVIDOR', 'RAMAIS', 'TRONCOS', 'OPÇÕES DE URA', 'DIRECIONAMENTOS', 'REDES', '24/11/2026 10:00', 'Nenhum registro cadastrado.', 'Rede #7', '192.0.2.53', 'Observação fictícia 1'] as $text) {
    $assert(str_contains($html, $text), 'conteúdo esperado: ' . $text);
}
$assert(substr_count($html, '••••••••') >= 3, 'senhas configuradas mascaradas');
$assert(str_contains($html, 'Não configurada'), 'senha ausente não é inventada');
$assert(str_contains($html, '&lt;b&gt;fictício&lt;/b&gt;') && !str_contains($html, '<b>fictício</b>'), 'HTML escapado');
$assert(!str_contains($html, '_encrypted') && !str_contains($html, 'created_by') && !str_contains($html, 'company_id'), 'campos internos fora do PDF');
$assert(str_contains($html, str_repeat('X', 26) . '<br>'), 'texto longo quebrado sem corte');

$redacted = NewsicExport::redact(['id' => 1, 'web_password_encrypted' => 'TEXTO-CIFRADO', 'ssh_password_encrypted' => '']);
$assert(!str_contains(json_encode($redacted, JSON_THROW_ON_ERROR), 'TEXTO-CIFRADO'), 'texto cifrado removido');
$assert($redacted['has_web_password'] === true && $redacted['has_ssh_password'] === false, 'indicadores de senha');

$many = [];
for ($n = 1; $n <= 140; $n++) $many[] = $extension($n);
$pdf = $report->render($company, $instance, ['ramais' => $many] + $sections, new DateTimeImmutable('2026-11-24 10:00:00'));
$assert(str_starts_with($pdf, '%PDF-'), 'PDF renderizado');
$assert((int) preg_match_all('/\/Type\s*\/Page(?![a-z])/', $pdf) > 1, 'paginação automática');
echo "OK: ficha técnica PDF A4, seções, senhas mascaradas, escape, quebra de texto e paginação.\n";
