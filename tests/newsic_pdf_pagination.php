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

$widthCases = [
    'ramais' => [9, 11, 15, 14, 16, 9, 18, 8],
    'troncos' => [17, 12, 15, 10, 9, 22, 15],
    'ura' => [18, 14, 18, 20, 30],
    'roteamentos' => [10, 14, 20, 20, 16, 20],
];
foreach ($widthCases as $section => $weights) {
    foreach (['curta', str_repeat('Observação extensa fictícia. ', 120)] as $note) {
        $html = $report->html($company, $instance, [$section => [['notes' => $note]]], $at);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        $headers = $xpath->query('//table[@class="grid"]/thead/tr/th[starts-with(@class, "col")]');
        $cells = $xpath->query('//table[@class="grid"]/tbody/tr/td[not(@colspan)] | //table[@class="record-grid"]/tbody/tr/td[not(@colspan)]');
        $assert($headers->length === count($weights) && $cells->length === count($weights), 'quantidade de colunas: ' . $section);
        $sizing = $xpath->query('//table[@class="grid"]/thead/tr[1][@class="column-widths"]/th[not(@colspan)]');
        $assert($sizing->length === count($weights), 'primeira linha define cada coluna antes dos colspans: ' . $section);
        foreach ($weights as $index => $weight) {
            $expected = 'width:' . number_format(100 * $weight / array_sum($weights), 6, '.', '') . '%';
            $assert($sizing->item($index)->getAttribute('style') === $expected, 'largura da primeira linha: ' . $section);
            $assert($headers->item($index)->getAttribute('style') === $expected, 'largura explícita do cabeçalho: ' . $section);
            $assert($cells->item($index)->getAttribute('style') === $expected, 'largura explícita da célula: ' . $section);
        }
        $bars = $xpath->query('//table[@class="grid"]/thead/tr/th[contains(@class, "bar")]');
        $assert($bars->length === 2, 'título e contador repetíveis: ' . $section);
        $sum = 0.0;
        foreach ($bars as $bar) $sum += (float) substr($bar->getAttribute('style'), 6);
        $assert(abs($sum - 100) < 0.00001, 'larguras dos cabeçalhos agrupados: ' . $section);
        if ($section === 'ramais') {
            $assert($headers->item(4)->textContent === 'DEPARTAMENTO' && $headers->item(4)->getElementsByTagName('br')->length === 1, 'departamento completo com quebra controlada');
            $assert($headers->item(5)->textContent === 'GRAVAÇÃO' && $headers->item(5)->getElementsByTagName('br')->length === 1, 'gravação completa com quebra controlada');
        }
        foreach ($weights as $index => $weight) {
            preg_match('/al-[lcr]/', $headers->item($index)->getAttribute('class'), $headerAlign);
            preg_match('/al-[lcr]/', $cells->item($index)->getAttribute('class'), $cellAlign);
            $assert(($headerAlign[0] ?? '') !== '' && ($headerAlign[0] ?? '') === ($cellAlign[0] ?? ''), 'alinhamento do dado igual ao do cabeçalho: ' . $section . ' coluna ' . ($index + 1));
        }

        $geometry = ['headers' => [], 'cells' => []];
        $options = new Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $renderer = new Dompdf\Dompdf($options);
        $renderer->setPaper('A4', 'portrait');
        $renderer->setCallbacks([['event' => 'end_frame', 'f' => static function ($frame) use (&$geometry): void {
            $node = $frame->get_node();
            if (!$node instanceof DOMElement) return;
            if ($node->tagName === 'th' && str_starts_with($node->getAttribute('class'), 'col')) {
                $geometry['headers'][] = $frame->get_border_box();
            } elseif ($node->tagName === 'td' && !$node->hasAttribute('colspan') && str_starts_with($node->getAttribute('style'), 'width:')) {
                $geometry['cells'][] = $frame->get_border_box();
            }
        }]]);
        $renderer->loadHtml($html, 'UTF-8');
        $renderer->render();
        $assert(count($geometry['headers']) >= count($weights) && count($geometry['cells']) === count($weights), 'captura da geometria renderizada: ' . $section);
        foreach ($weights as $index => $weight) {
            [$hx, , $hw] = $geometry['headers'][$index];
            [$cx, , $cw] = $geometry['cells'][$index];
            $assert(abs($hx - $cx) <= 1.0 && abs(($hx + $hw) - ($cx + $cw)) <= 1.0, 'divisórias renderizadas alinhadas (tolerância 1 pt): ' . $section . ' coluna ' . ($index + 1));
        }
        unset($renderer);
    }
}

$metrics = (new Dompdf\Dompdf())->getFontMetrics();
$measureFont = $metrics->getFont('DejaVu Sans');
$wideFields = [
    'ramais' => ['extension_number', 'ip_address', 'user_name', 'department'],
    'troncos' => ['pilot_number', 'carrier', 'channel_quantity', 'ddrs'],
    'ura' => ['ura_type', 'option', 'schedule', 'recording', 'destination'],
    'roteamentos' => ['ipo', 'number', 'name', 'forwarding', 'recording', 'destination'],
];
foreach ($wideFields as $section => $fields) {
    $weights = $widthCases[$section];
    foreach (['W', 'AbC123', 'i'] as $unit) {
        $row = ['notes' => 'curta'];
        foreach ($fields as $field) $row[$field] = str_repeat($unit, 80);
        $html = $report->html($company, $instance, [$section => [$row]], $at);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        $cells = $xpath->query('//table[@class="grid"]/tbody/tr/td[not(@colspan)] | //table[@class="record-grid"]/tbody/tr/td[not(@colspan)]');
        $assert($cells->length === count($weights), 'colunas na verificação de texto longo: ' . $section);
        $checked = 0;
        foreach ($weights as $index => $weight) {
            $inner = 544.28 * $weight / array_sum($weights) - 8.5;
            $text = '';
            foreach ($cells->item($index)->childNodes as $child) {
                if (!$child instanceof DOMText) continue;
                $text .= $child->textContent;
                $width = $metrics->getTextWidth($child->textContent, $measureFont, 8.0);
                $assert($width <= $inner, 'texto cabe na coluna ' . ($index + 1) . ' de ' . $section . ' (' . $unit . '): ' . round($width, 1) . ' > ' . round($inner, 1));
                $checked++;
            }
            if (in_array($index, array_keys($fields), true) || str_repeat($unit, 80) === $text) {
                $assert($text === '' || str_starts_with($text, $unit) || $text === '—', 'conteúdo preservado sem corte na coluna ' . ($index + 1) . ' de ' . $section);
            }
        }
        $assert($checked >= count($fields), 'textos longos verificados: ' . $section);
        $all = '';
        foreach ($cells as $cell) $all .= $cell->textContent;
        $assert(substr_count($all, $unit) >= 80 * min(count($fields), 4) - 80 * 3 * ($unit === 'AbC123' ? 1 : 0), 'nenhum caractere removido: ' . $section);
    }
}
echo "OK: estrutura de seções vazias, registro com observação, redes, continuação identificada, larguras explícitas, alinhamento igual ao cabeçalho, geometria renderizada e texto longo contido nas colunas. Inspeção visual pendente.\n";
