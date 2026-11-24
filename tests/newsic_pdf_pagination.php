<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$assert = static function (bool $value, string $label): void {
    if (!$value) throw new RuntimeException('Falha paginação Newsic: ' . $label);
};
$report = new NewDoc\NewsicPdfReport();
$company = ['name' => 'Empresa fictícia'];
$instance = ['id' => 1, 'server_name' => 'Servidor fictício', 'status' => 'ativo'];
$at = new DateTimeImmutable('2026-11-24 10:00:00');
$html = $report->html($company, $instance, [], $at);
$doc = new DOMDocument();
$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($doc);
$assert($xpath->query('//div[@class="empty-section keep"]')->length === 5, 'cinco seções vazias agrupadas');
$assert($xpath->query('//div[@class="empty-section keep"]//th')->length === 0, 'sem cabeçalhos de colunas em seção vazia');
$sections = [
    'ramais' => [['extension_number' => '2004', 'notes' => 'Observação do ramal 2004']],
    'redes' => [['id' => 7, 'gateway' => '192.0.2.1', 'notes' => 'Observação da rede 7']],
];
$html = $report->html($company, $instance, $sections, $at);
$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($doc);
$units = $xpath->query('//tr[@class="record-unit"]');
$assert($units->length === 1 && str_contains($units->item(0)->textContent, '2004') && str_contains($units->item(0)->textContent, 'Observação do ramal 2004'), 'registro e observação na mesma linha externa');
$cards = $xpath->query('//div[@class="network-card keep"]');
$assert($cards->length === 1 && str_contains($cards->item(0)->textContent, 'Rede #7') && str_contains($cards->item(0)->textContent, 'Observação da rede 7'), 'rede moderada agrupada');
$sections['ramais'][0]['notes'] = str_repeat('Observação extensa fictícia. ', 120);
$sections['redes'][0]['notes'] = str_repeat('Observação extensa fictícia. ', 120);
$html = $report->html($company, $instance, $sections, $at);
$assert(str_contains($html, 'Observações — Ramais · registro 1 · 2004:'), 'observação extensa mantém identificação');
$assert(str_contains($html, '<thead><tr><th class="cardh" colspan="4">Rede #7</th></tr></thead>'), 'rede extensa tem identificação repetível');
$pdf = $report->render($company, $instance, $sections, $at);
$assert(str_starts_with($pdf, '%PDF-') && strlen($pdf) > 1000, 'cenário extenso renderiza');
echo "OK: estrutura de seções vazias, registro com observação, redes e continuação identificada. Inspeção visual pendente.\n";
