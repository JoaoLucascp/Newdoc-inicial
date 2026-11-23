<?php

declare(strict_types=1);

namespace NewDoc;

use PDO;
use RuntimeException;
use Throwable;
use Dompdf\Dompdf;
use Dompdf\Options;

final class NewsicExport
{
    private const FIELDS = [
        'servidor' => 'id,company_id,server_name,internal_ip,external_ip,web_port,web_username,web_password_encrypted,ssh_port,ssh_username,ssh_password_encrypted,server_type,freemyip_link,software_version,documentation_path,status,notes,source_document_id,created_at,updated_at,created_by,updated_by',
        'ramais' => 'id,newsic_instance_id,extension_number,registration_password_encrypted,ip_address,user_name,department,call_recording,permission_lof,permission_loc,permission_ddf,permission_ddc,permission_ddi,permission_srv,status,notes,created_at,updated_at',
        'troncos' => 'id,newsic_instance_id,pilot_number,trunk_type,carrier,receives_calls,channel_quantity,ddrs,status,notes,created_at,updated_at',
        'ura' => 'id,newsic_instance_id,ura_type,option,schedule,recording,destination,notes,created_at,updated_at',
        'roteamentos' => 'id,newsic_instance_id,ipo,number,name,forwarding,recording,destination,notes,created_at,updated_at',
        'redes' => 'id,newsic_instance_id,gateway,network_type,interface_ip,subnet_mask,dns,proxy_ip,proxy_port,proxy_type,audio_ip,audio_subnet_mask,audio_gateway,notes,created_at,updated_at',
    ];
    private const TABLES = ['ramais' => 'newsic_extensions', 'troncos' => 'newsic_trunks', 'ura' => 'newsic_ura_options', 'roteamentos' => 'newsic_routings', 'redes' => 'newsic_networks'];

    public function __construct(private PDO $pdo) {}

    /** Generates an authorized report; the HTTP layer must separately validate CSRF. */
    public function generate(int $instanceId, string $format, bool $confirmed = false): array
    {
        if (!$confirmed || $instanceId < 1 || !in_array($format, ['csv', 'pdf'], true)) {
            throw new RuntimeException('Confirmação, servidor ou formato inválido.');
        }
        $user = Auth::user();
        if (!Auth::canManage() || !is_int($user['id'] ?? null) || $user['id'] < 1) {
            throw new RuntimeException('Exportação não autorizada.');
        }
        $check = $this->pdo->prepare('SELECT role, active FROM users WHERE id = ?');
        $check->execute([$user['id']]);
        $storedUser = $check->fetch(PDO::FETCH_ASSOC);
        if (!$storedUser || (int) $storedUser['active'] !== 1 || !in_array($storedUser['role'], ['admin', 'technician'], true)) {
            throw new RuntimeException('Exportação não autorizada.');
        }
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('A exportação exige transação própria.');
        }
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT ' . self::FIELDS['servidor'] . ' FROM newsic_instances WHERE id = ?');
            $query->execute([$instanceId]);
            $instance = $query->fetch(PDO::FETCH_ASSOC);
            if (!$instance) throw new RuntimeException('Servidor não encontrado.');
            $companyQuery = $this->pdo->prepare('SELECT id, name FROM companies WHERE id = ?');
            $companyQuery->execute([$instance['company_id']]);
            $company = $companyQuery->fetch(PDO::FETCH_ASSOC);
            if (!$company) throw new RuntimeException('Empresa não encontrada.');
            $rows = [
                ['documento', '0', 'aviso', 'CONFIDENCIAL: contém senhas legíveis. Guarde em local protegido.'],
                ['documento', '0', 'gerado_em', date(DATE_ATOM)],
                ['documento', '0', 'formato', $format],
            ];
            if ($format === 'csv') $rows[] = ['documento', '0', 'protecao_csv', 'Valores iniciados por =, +, -, @ ou controles, inclusive após espaços, recebem apóstrofo de proteção. Não é modelo de importação.'];
            $this->append($rows, 'empresa', $company);
            $this->append($rows, 'servidor', $instance);
            foreach (self::TABLES as $section => $table) {
                $query = $this->pdo->prepare('SELECT ' . self::FIELDS[$section] . ' FROM ' . $table . ' WHERE newsic_instance_id = ? ORDER BY id');
                $query->execute([$instanceId]);
                $count = 0;
                while ($record = $query->fetch(PDO::FETCH_ASSOC)) {
                    $this->append($rows, $section, $record);
                    $count++;
                }
                $rows[] = ['documento', '0', 'total_' . $section, (string) $count];
            }
            $content = $format === 'csv' ? $this->csv($rows) : $this->pdf($rows);
            Audit::record($this->pdo, 'consulta', 'newsic_instances', $instanceId, 'exportacao_' . $format, (int) $instance['company_id'], true);
            $this->pdo->commit();
            return [
                'content' => $content,
                'content_type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/pdf',
                'filename' => 'newsic-' . $instanceId . '-' . date('Ymd-His') . '.' . $format,
            ];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new RuntimeException('Não foi possível gerar a exportação. Nenhum arquivo foi disponibilizado.');
        }
    }

    private function append(array &$rows, string $section, array $record): void
    {
        foreach ($record as $field => $value) {
            if (str_ends_with($field, '_encrypted')) {
                $field = substr($field, 0, -10);
                $value = $value === null || $value === '' ? '' : CredentialCipher::decrypt((string) $value);
            }
            $rows[] = [$section, (string) $record['id'], $field, (string) ($value ?? '')];
        }
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://memory', 'w+');
        if ($stream === false) throw new RuntimeException('Memória indisponível.');
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            array_unshift($rows, ['secao', 'registro_id', 'campo', 'valor']);
            foreach ($rows as $row) {
                $safe = array_map(static function (string $value): string {
                    return preg_match('/^(?:[\x00-\x20]*[=+@-]|[\x00-\x1F])/', $value) === 1 ? "'" . $value : $value;
                }, $row);
                if (fputcsv($stream, $safe, ';', '"', '', "\r\n") === false) throw new RuntimeException('Falha no CSV.');
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            if ($content === false) throw new RuntimeException('Falha no CSV.');
            return $content;
        } finally {
            fclose($stream);
        }
    }

    private function pdf(array $rows): string
    {
        if (!class_exists(Dompdf::class)) throw new RuntimeException('Biblioteca PDF indisponível.');
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><style>@page{margin:28px}body{font-family:"DejaVu Sans",sans-serif;font-size:9pt}h1{font-size:15pt}table{width:100%;border-collapse:collapse;table-layout:fixed}td,th{border:1px solid #bbb;padding:4px;overflow-wrap:break-word}td{white-space:pre-wrap}thead{display:table-header-group}th{width:25%}</style></head><body><h1>NewDOC — Exportação Newsic</h1><p>CONFIDENCIAL — senhas legíveis. Documento gerado do cadastro NewDOC, sem consulta ao equipamento.</p><table><thead><tr><th>Seção</th><th>Registro</th><th>Campo</th><th>Valor</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            // Small chunks avoid Dompdf's inability to paginate a single oversized cell.
            $chunks = mb_str_split($row[3], 100);
            if ($chunks === []) $chunks = [''];
            foreach ($chunks as $index => $chunk) {
                $html .= '<tr><td>' . $escape($row[0]) . '</td><td>' . $escape($row[1]) . '</td><td>' . $escape($row[2] . ($index > 0 ? ' (continuação)' : '')) . '</td><td>' . $escape($chunk) . '</td></tr>';
            }
        }
        $html .= '</tbody></table></body></html>';
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        return $dompdf->output();
    }
}
