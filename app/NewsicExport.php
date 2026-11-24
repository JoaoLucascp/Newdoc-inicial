<?php

declare(strict_types=1);

namespace NewDoc;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

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

    /**
     * PDF never contains credentials and needs no secret confirmation; CSV keeps readable
     * credentials and requires explicit confirmation. CSRF is validated by the HTTP layer.
     */
    public function generate(int $instanceId, string $format, bool $confirmed = false): array
    {
        if ($instanceId < 1 || !in_array($format, ['csv', 'pdf'], true) || ($format === 'csv' && !$confirmed)) {
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
            $sections = [];
            foreach (self::TABLES as $section => $table) {
                $query = $this->pdo->prepare('SELECT ' . self::FIELDS[$section] . ' FROM ' . $table . ' WHERE newsic_instance_id = ? ORDER BY id');
                $query->execute([$instanceId]);
                $sections[$section] = $query->fetchAll(PDO::FETCH_ASSOC);
            }
            if ($format === 'csv') {
                $content = $this->csv($this->csvRows($company, $instance, $sections));
            } else {
                $safe = array_map(static fn (array $records): array => array_map([self::class, 'redact'], $records), $sections);
                $content = (new NewsicPdfReport())->render($company, self::redact($instance), $safe, new DateTimeImmutable());
            }
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

    /** Replaces ciphertext columns with a has_* flag so credentials never reach the PDF layout. */
    public static function redact(array $record): array
    {
        $safe = [];
        foreach ($record as $field => $value) {
            if (str_ends_with((string) $field, '_encrypted')) {
                $safe['has_' . substr((string) $field, 0, -10)] = $value !== null && $value !== '';
            } else {
                $safe[$field] = $value;
            }
        }
        return $safe;
    }

    private function csvRows(array $company, array $instance, array $sections): array
    {
        $rows = [
            ['documento', '0', 'aviso', 'CONFIDENCIAL: contém senhas legíveis. Guarde em local protegido.'],
            ['documento', '0', 'gerado_em', date(DATE_ATOM)],
            ['documento', '0', 'formato', 'csv'],
            ['documento', '0', 'protecao_csv', 'Valores iniciados por =, +, -, @ ou controles, inclusive após espaços, recebem apóstrofo de proteção. Não é modelo de importação.'],
        ];
        $this->append($rows, 'empresa', $company);
        $this->append($rows, 'servidor', $instance);
        foreach ($sections as $section => $records) {
            foreach ($records as $record) $this->append($rows, $section, $record);
            $rows[] = ['documento', '0', 'total_' . $section, (string) count($records)];
        }
        return $rows;
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
}
