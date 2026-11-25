<?php

declare(strict_types=1);

namespace NewDoc;

use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use RuntimeException;

/** Renders the Newsic technical sheet without credential values. */
final class NewsicPdfReport
{
    private const MASK = '••••••••';
    private const STATUS = ['ativo' => ['Ativo', 'ok'], 'inativo' => ['Inativo', 'off'], 'manutencao' => ['Manutenção', 'warn']];
    private const SERVER_TYPES = ['virtual' => 'Virtual', 'cloud' => 'Cloud', 'fisico' => 'Físico'];
    private const TRUNK_TYPES = ['sip_trunk' => 'SIP Trunk'];
    private const PERMISSIONS = ['permission_lof' => 'LOF', 'permission_loc' => 'LOC', 'permission_ddf' => 'DDF', 'permission_ddc' => 'DDC', 'permission_ddi' => 'DDI', 'permission_srv' => 'SRV'];
    /** A4 width (595.28pt) minus 34px page margins on both sides, in points. */
    private const CONTENT_WIDTH = 544.28;
    /** Conservative inset for cell padding and borders, in points. */
    private const CELL_INSET = 12.0;
    private const CELL_FONT_SIZE = 8.0;

    private ?Dompdf $measurer = null;
    private ?FontMetrics $metrics = null;
    private ?string $measureFont = null;
    /** @var array<string, float> */
    private array $charWidths = [];

    public function render(array $company, array $instance, array $sections, DateTimeImmutable $at): string
    {
        if (!class_exists(Dompdf::class)) throw new RuntimeException('Biblioteca PDF indisponível.');
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->html($company, $instance, $sections, $at), 'UTF-8');
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 128, $canvas->get_height() - 27, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 8, [0.27, 0.33, 0.42]);
        $output = $dompdf->output();
        if (!is_string($output) || $output === '') throw new RuntimeException('Falha ao renderizar o PDF.');
        return $output;
    }

    public function html(array $company, array $instance, array $sections, DateTimeImmutable $at): string
    {
        $id = str_pad((string) (int) ($instance['id'] ?? 0), 3, '0', STR_PAD_LEFT);
        $h = '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><style>' . $this->css() . '</style></head><body>';
        $h .= '<div id="hdr"><table class="w"><tr><td><span class="b1">NEWSIC</span><br><span class="b2">IPBX · Documentação técnica</span></td><td class="r"><span class="n1">NEWTEL</span> <span class="n2">SOLUÇÕES</span></td></tr></table></div>';
        $h .= '<div id="ftr"><b>NEWTEL SOLUÇÕES</b> · Documento confidencial · Newsic · Servidor #' . $this->e($id) . '</div>';
        $h .= '<h1>FICHA TÉCNICA DO SERVIDOR</h1><div class="sub">Inventário de infraestrutura e configuração · ' . $this->e((string) ($company['name'] ?? '')) . '</div>';
        $h .= '<table class="strip"><tr><td><b>ID:</b> ' . $this->e($id) . '</td><td><b>Versão:</b> ' . $this->text($instance['software_version'] ?? null) . '</td><td><b>Gerado em:</b> ' . $this->e($at->format('d/m/Y H:i')) . '</td><td class="r">' . $this->badge((string) ($instance['status'] ?? '')) . '</td></tr></table>';
        $h .= $this->server($company, $instance);
        $h .= $this->extensions($sections['ramais'] ?? []);
        $h .= $this->trunks($sections['troncos'] ?? []);
        $h .= $this->ura($sections['ura'] ?? []);
        $h .= $this->routings($sections['roteamentos'] ?? []);
        $h .= $this->networks($sections['redes'] ?? []);
        return $h . '</body></html>';
    }

    private function server(array $company, array $i): string
    {
        $pairs = [
            ['Instância', $this->text($i['server_name'] ?? null)],
            ['Tipo', $this->text(self::SERVER_TYPES[$i['server_type'] ?? ''] ?? ($i['server_type'] ?? null))],
            ['IP interno', $this->text($i['internal_ip'] ?? null)],
            ['IP externo', $this->text($i['external_ip'] ?? null)],
            ['Porta web', $this->text($i['web_port'] ?? null)],
            ['Usuário web', $this->text($i['web_username'] ?? null)],
            ['Senha web', $this->secret($i['has_web_password'] ?? false)],
            ['Empresa', $this->text($company['name'] ?? null)],
            ['Porta SSH', $this->text($i['ssh_port'] ?? null)],
            ['Usuário SSH', $this->text($i['ssh_username'] ?? null)],
            ['Senha SSH', $this->secret($i['has_ssh_password'] ?? false)],
            ['Link FreeMyIP', $this->text($i['freemyip_link'] ?? null)],
            ['Documentação', $this->text($i['documentation_path'] ?? null)],
            ['Criada em', $this->date($i['created_at'] ?? null)],
            ['Atualizada em', $this->date($i['updated_at'] ?? null)],
        ];
        return $this->bar('Dados do servidor', null) . $this->kv($pairs, $i['notes'] ?? null)
            . '<div class="notice"><b>CREDENCIAIS PROTEGIDAS</b> — senhas ocultas nesta exportação. Para consulta, use o painel do NewDOC.</div>';
    }

    /** Maximum text width, in points, that fits inside each column. */
    private function limits(array $columns): array
    {
        $total = array_sum(array_column($columns, 1));
        return array_map(static fn (array $column): float => max(20.0, self::CONTENT_WIDTH * $column[1] / $total - self::CELL_INSET), $columns);
    }

    private function extensions(array $rows): string
    {
        $columns = [['Ramal', 9, 'l'], ['Senha', 11, 'l'], ['IP', 15, 'l'], ['Usuário', 14, 'l'], ['Departamento', 16, 'l'], ['Gravação', 9, 'l'], ['Permissões', 18, 'l'], ['Status', 8, 'l']];
        $w = $this->limits($columns);
        $out = [];
        foreach ($rows as $r) {
            $on = [];
            $off = [];
            foreach (self::PERMISSIONS as $key => $label) {
                if ((int) ($r[$key] ?? 0) === 1) $on[] = $label; else $off[] = $label;
            }
            $perm = $on ? $this->e(implode(', ', $on)) : '<span class="muted">Nenhuma</span>';
            if ($off) $perm .= '<br><span class="muted">Sem: ' . $this->e(implode(', ', $off)) . '</span>';
            $out[] = ['cells' => [
                $this->text($r['extension_number'] ?? null, $w[0]), $this->secret($r['has_registration_password'] ?? false, false),
                $this->text($r['ip_address'] ?? null, $w[2]), $this->text($r['user_name'] ?? null, $w[3]), $this->text($r['department'] ?? null, $w[4]),
                $this->yesNo($r['call_recording'] ?? null), $perm, $this->badge((string) ($r['status'] ?? '')),
            ], 'note' => $r['notes'] ?? null];
        }
        return $this->table('Ramais', $columns, $out);
    }

    private function trunks(array $rows): string
    {
        $columns = [['Número piloto', 17, 'l'], ['Tipo', 12, 'l'], ['Operadora', 15, 'l'], ['Recebe', 10, 'l'], ['Canais', 9, 'l'], ['DDRs', 22, 'l'], ['Status', 15, 'l']];
        $w = $this->limits($columns);
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['cells' => [
                $this->text($r['pilot_number'] ?? null, $w[0]), $this->text(self::TRUNK_TYPES[$r['trunk_type'] ?? ''] ?? ($r['trunk_type'] ?? null), $w[1]),
                $this->text($r['carrier'] ?? null, $w[2]), $this->yesNo($r['receives_calls'] ?? null), $this->text($r['channel_quantity'] ?? null, $w[4]),
                $this->text($r['ddrs'] ?? null, $w[5]), $this->badge((string) ($r['status'] ?? '')),
            ], 'note' => $r['notes'] ?? null];
        }
        return $this->table('Troncos', $columns, $out);
    }

    private function ura(array $rows): string
    {
        $columns = [['Tipo', 18, 'l'], ['Opção', 14, 'l'], ['Horário', 18, 'l'], ['Gravação', 20, 'l'], ['Destino', 30, 'l']];
        $w = $this->limits($columns);
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['cells' => [$this->text($r['ura_type'] ?? null, $w[0]), $this->text($r['option'] ?? null, $w[1]), $this->text($r['schedule'] ?? null, $w[2]), $this->text($r['recording'] ?? null, $w[3]), $this->text($r['destination'] ?? null, $w[4])], 'note' => $r['notes'] ?? null];
        }
        return $this->table('Opções de URA', $columns, $out);
    }

    private function routings(array $rows): string
    {
        $columns = [['IPO', 10, 'l'], ['Número', 14, 'l'], ['Nome', 20, 'l'], ['Encaminhamento', 20, 'l'], ['Gravação', 16, 'l'], ['Destino', 20, 'l']];
        $w = $this->limits($columns);
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['cells' => [$this->text($r['ipo'] ?? null, $w[0]), $this->text($r['number'] ?? null, $w[1]), $this->text($r['name'] ?? null, $w[2]), $this->text($r['forwarding'] ?? null, $w[3]), $this->text($r['recording'] ?? null, $w[4]), $this->text($r['destination'] ?? null, $w[5])], 'note' => $r['notes'] ?? null];
        }
        return $this->table('Direcionamentos', $columns, $out);
    }

    private function networks(array $rows): string
    {
        if ($rows === []) return $this->emptySection('Redes');
        $h = $this->bar('Redes', count($rows));
        foreach ($rows as $r) {
            $pairs = [
                ['Gateway', $this->text($r['gateway'] ?? null)], ['Tipo de rede', $this->text($r['network_type'] ?? null)],
                ['IP da interface', $this->text($r['interface_ip'] ?? null)], ['Máscara', $this->text($r['subnet_mask'] ?? null)],
                ['DNS', $this->text($r['dns'] ?? null)], ['IP do proxy', $this->text($r['proxy_ip'] ?? null)],
                ['Porta do proxy', $this->text($r['proxy_port'] ?? null)], ['Tipo de proxy', $this->text($r['proxy_type'] ?? null)],
                ['IP de áudio', $this->text($r['audio_ip'] ?? null)], ['Máscara de áudio', $this->text($r['audio_subnet_mask'] ?? null)],
                ['Gateway de áudio', $this->text($r['audio_gateway'] ?? null)], ['', ''],
            ];
            $label = 'Rede #' . (string) ($r['id'] ?? '');
            $heading = '<tr><th class="cardh" colspan="4">' . $this->e($label) . '</th></tr>';
            $content = $this->kv($pairs, $r['notes'] ?? null);
            $moderate = $this->moderate($content);
            if ($moderate) {
                $h .= '<div class="network-card keep">' . '<div class="cardh">' . $this->e($label) . '</div>' . $content . '</div>';
            } else {
                $content = str_replace('<table class="kv">', '<table class="kv"><thead>' . $heading . '</thead><tbody>', $content);
                $content = substr($content, 0, -8) . '</tbody></table>';
                $h .= $content;
            }
        }
        return $h;
    }

    private function emptySection(string $title): string
    {
        return '<div class="empty-section keep">' . $this->bar($title, 0)
            . '<div class="emptybox">Nenhum registro cadastrado.</div></div>';
    }

    /** Conservative grouping only; large content must remain breakable. */
    private function moderate(string $html): bool
    {
        return mb_strlen(strip_tags($html)) <= 1800 && substr_count($html, '<br>') <= 16;
    }

    /**
     * The first record travels with the title and column headers, so a page break
     * cannot leave an orphan header at the bottom of a page.
     */
    private function table(string $title, array $columns, array $rows): string
    {
        if ($rows === []) return $this->emptySection($title);
        $total = count($rows);
        $first = array_shift($rows);
        $h = '<div class="section-head keep">' . $this->grid($columns, [$first], $title, $total, false) . '</div>';
        if ($rows !== []) $h .= $this->grid($columns, $rows, $title, $total, true);
        return $h;
    }

    private function grid(array $columns, array $rows, string $title, int $total, bool $withBar): string
    {
        $n = count($columns);
        $totalWidth = array_sum(array_column($columns, 1));
        $widths = array_map(static fn (array $column): string => number_format(100 * $column[1] / $totalWidth, 6, '.', '') . '%', $columns);
        $colgroup = '<colgroup>';
        foreach ($widths as $width) $colgroup .= '<col style="width:' . $width . '">';
        $colgroup .= '</colgroup>';
        // Dompdf fixed layout reads only the first row and ignores widths on colspan cells.
        $sizing = '<tr class="column-widths" aria-hidden="true">';
        foreach ($widths as $width) $sizing .= '<th style="width:' . $width . '"></th>';
        $sizing .= '</tr>';
        $h = '<table class="grid' . ($withBar ? '' : ' head-grid') . '">' . $colgroup . '<thead>' . $sizing;
        if (!$withBar) {
            $headingWidth = number_format(100 * array_sum(array_slice(array_column($columns, 1), 0, $n - 2)) / $totalWidth, 6, '.', '') . '%';
            $countWidth = number_format(100 * array_sum(array_slice(array_column($columns, 1), -2)) / $totalWidth, 6, '.', '') . '%';
            $h .= '<tr><th class="bar" style="width:' . $headingWidth . '" colspan="' . ($n - 2) . '">' . $this->e(mb_strtoupper($title)) . '</th><th class="bar r" style="width:' . $countWidth . '" colspan="2"><span class="pill">' . $total . ' registro(s)</span></th></tr><tr>';
            foreach ($columns as $index => [$label, $width, $align]) {
                $h .= '<th class="col al-' . $align . '" style="width:' . $widths[$index] . '">' . $this->e(mb_strtoupper($label)) . '</th>';
            }
            $h .= '</tr>';
        }
        $h .= '</thead><tbody>';
        foreach ($rows as $index => $row) {
            $data = '<tr class="' . ($index % 2 ? 'alt' : '') . '">';
            foreach ($row['cells'] as $cellIndex => $cell) $data .= '<td class="al-' . $columns[$cellIndex][2] . '" style="width:' . $widths[$cellIndex] . '">' . $cell . '</td>';
            $data .= '</tr>';
            $note = trim((string) ($row['note'] ?? ''));
            $noteHtml = $note === '' ? '' : '<tr class="note"><td colspan="' . $n . '"><b>Observações:</b> ' . $this->text($note) . '</td></tr>';
            if ($this->moderate($data . $noteHtml)) {
                $h .= '<tr class="record-unit"><td class="record-shell" colspan="' . $n . '"><table class="record-grid">' . $colgroup . '<tbody>' . $data . $noteHtml . '</tbody></table></td></tr>';
            } else {
                $h .= $data;
                if ($note !== '') {
                    $context = $this->e($title) . ' · registro ' . ($index + 1) . ' · ' . strip_tags($row['cells'][0], '<br>');
                    foreach (mb_str_split($note, 600) as $part) {
                        $h .= '<tr class="note"><td colspan="' . $n . '"><b>Observações — ' . $context . ':</b> ' . $this->text($part) . '</td></tr>';
                    }
                }
            }
        }
        return $h . '</tbody></table>';
    }

    private function bar(string $title, ?int $count): string
    {
        return '<table class="barbox"><tr><td class="bt">' . $this->e(mb_strtoupper($title)) . '</td>'
            . ($count === null ? '' : '<td class="bc"><span class="pill">' . $count . ' registro(s)</span></td>') . '</tr></table>';
    }

    private function kv(array $pairs, mixed $note): string
    {
        $h = '<table class="kv">';
        foreach (array_chunk($pairs, 2) as $row) {
            $h .= '<tr>';
            foreach ($row as [$label, $value]) $h .= '<td class="l">' . $this->e($label) . '</td><td class="v">' . $value . '</td>';
            $h .= '</tr>';
        }
        if (trim((string) $note) !== '') {
            foreach (mb_str_split((string) $note, 600) as $part) $h .= '<tr><td class="l">Observações</td><td class="v" colspan="3">' . $this->text($part) . '</td></tr>';
        }
        return $h . '</table>';
    }

    private function secret(mixed $configured, bool $label = true): string
    {
        if (!$configured) return '<span class="muted">Não configurada</span>';
        return '<span class="mask">' . self::MASK . '</span>' . ($label ? ' <span class="muted">protegida</span>' : '');
    }

    private function badge(string $status): string
    {
        [$label, $class] = self::STATUS[$status] ?? [$status, 'off'];
        return $label === '' ? '<span class="muted">—</span>' : '<span class="badge ' . $class . '">' . $this->e($label) . '</span>';
    }

    private function yesNo(mixed $value): string
    {
        return $value === null || $value === '' ? '<span class="muted">—</span>' : ((int) $value === 1 ? 'Sim' : 'Não');
    }

    private function date(mixed $value): string
    {
        $time = is_string($value) ? strtotime($value) : false;
        return $time === false ? $this->text($value) : $this->e(date('d/m/Y H:i', $time));
    }

    /** Without $maxWidth, long tokens are split every 26 characters; with it, by measured width in points. */
    private function text(mixed $value, ?float $maxWidth = null): string
    {
        $s = trim((string) ($value ?? ''));
        if ($s === '') return '<span class="muted">—</span>';
        $out = '';
        foreach (preg_split('/(\s+)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (preg_match('/^\s+$/u', $part) === 1) {
                $lines = substr_count($part, "\n");
                $out .= $lines > 0 ? str_repeat('<br>', $lines) : ' ';
                continue;
            }
            $pieces = $maxWidth === null ? mb_str_split($part, 26) : $this->wrap($part, $maxWidth);
            $out .= implode('<br>', array_map($this->e(...), $pieces));
        }
        return $out;
    }

    /** @return list<string> */
    private function wrap(string $token, float $maxWidth): array
    {
        $pieces = [];
        $current = '';
        $width = 0.0;
        foreach (mb_str_split($token) as $char) {
            $charWidth = $this->charWidth($char);
            if ($current !== '' && $width + $charWidth > $maxWidth) {
                $pieces[] = $current;
                $current = '';
                $width = 0.0;
            }
            $current .= $char;
            $width += $charWidth;
        }
        if ($current !== '') $pieces[] = $current;
        return $pieces;
    }

    private function charWidth(string $char): float
    {
        if (isset($this->charWidths[$char])) return $this->charWidths[$char];
        if ($this->metrics === null) {
            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $this->measurer = new Dompdf($options);
            $this->metrics = $this->measurer->getFontMetrics();
            $this->measureFont = $this->metrics->getFont('DejaVu Sans');
        }
        // 5% safety margin for kerning and rounding differences.
        return $this->charWidths[$char] = $this->metrics->getTextWidth($char, $this->measureFont, self::CELL_FONT_SIZE) * 1.05;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function css(): string
    {
        return <<<'CSS'
@page{margin:118px 34px 72px 34px}
body{font-family:'DejaVu Sans',sans-serif;font-size:8.5pt;color:#1b2430}
.w{width:100%}.r{text-align:right}
table{border-collapse:collapse}
#hdr{position:fixed;top:-100px;left:0;right:0;height:84px;border-bottom:2px solid #1f6feb}
#ftr{position:fixed;bottom:-50px;left:0;right:0;height:30px;border-top:2px solid #1f6feb;padding-top:6px;font-size:7.5pt;color:#44546a}
.b1{font-size:24pt;font-weight:bold;color:#0b2545}.b2{font-size:8pt;color:#44546a}
.n1{font-size:15pt;font-weight:bold;color:#0b2545}.n2{font-size:15pt;color:#1f6feb}
h1{font-size:20pt;color:#0b2545;margin:0 0 2px 0}
.sub{font-size:10pt;color:#44546a;margin-bottom:10px}
.strip{width:100%;background:#eef3fa;border:1px solid #cfd9e8;margin-bottom:12px}
.strip td{padding:7px 9px;font-size:9pt;color:#0b2545}
.barbox{width:100%;background:#0b2545;margin-top:6px;page-break-after:avoid}
.bt{color:#fff;font-size:10pt;font-weight:bold;padding:6px 8px}
.bc{text-align:right;white-space:nowrap;padding:6px 8px}
.grid{width:100%;margin:6px 0 14px 0;table-layout:fixed}
.head-grid{margin-bottom:0}
.section-head{page-break-inside:avoid}
.column-widths th{height:0;line-height:0;font-size:0;padding:0;border:0}
th.bar{background:#0b2545;color:#fff;font-size:10pt;text-align:left;padding:6px 8px}
th.bar.r{text-align:right;white-space:nowrap}
th.col{background:#e8eef7;color:#0b2545;font-size:7pt;border:1px solid #cfd9e8;padding:4px 5px;white-space:nowrap}
td{border:1px solid #d6deea;padding:4px 5px;vertical-align:top;font-size:8pt;word-wrap:break-word;overflow-wrap:break-word;white-space:normal}
.al-l{text-align:left}.al-c{text-align:center}.al-r{text-align:right}
tr.alt td{background:#f6f9fd}
tr.note td{background:#f1f5fb;color:#33445c;font-size:7.5pt}
.keep,.record-unit{page-break-inside:avoid}
.record-shell{padding:0;border:0}
.record-grid{width:100%;margin:0;table-layout:fixed}
td.empty,.emptybox{text-align:center;color:#6b7a90;padding:10px}
.emptybox{border:1px solid #d6deea;margin-bottom:14px}
.pill{background:#1f6feb;color:#fff;font-size:7pt;padding:2px 7px;white-space:nowrap}
.badge{font-size:7.5pt;font-weight:bold;padding:2px 6px}
.badge.ok{background:#d9f5e3;color:#0d6b34}.badge.off{background:#e6e9ee;color:#46505e}.badge.warn{background:#fff0cc;color:#7a5200}
.muted{color:#8a96a8}.mask{color:#0b2545;font-weight:bold}
.notice{border:1px solid #9cc2ff;background:#e8f1ff;color:#0b3d91;padding:7px 9px;margin:8px 0 14px 0;font-size:8pt}
.kv{width:100%;margin-bottom:6px}
.kv td.l{width:17%;background:#f6f9fd;color:#44546a;font-weight:bold}
.kv td.v{width:33%}
.cardh{background:#e8eef7;color:#0b2545;font-weight:bold;padding:4px 8px;border:1px solid #cfd9e8;margin-top:8px;text-align:left;page-break-after:avoid}
CSS;
    }
}
