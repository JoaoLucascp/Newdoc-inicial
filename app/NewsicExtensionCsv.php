<?php

declare(strict_types=1);

namespace NewDoc;

use InvalidArgumentException;

final class NewsicExtensionCsv
{
    private const HEADERS = [
        'ramal' => 'extension_number', 'senha' => 'registration_password',
        'ip' => 'ip_address', 'usuario' => 'user_name', 'departamento' => 'department',
        'gravacao' => 'call_recording', 'lof' => 'permission_lof', 'loc' => 'permission_loc',
        'ddf' => 'permission_ddf', 'ddc' => 'permission_ddc', 'ddi' => 'permission_ddi',
        'srv' => 'permission_srv', 'status' => 'status', 'observacoes' => 'notes',
    ];
    private const FLAGS = [
        'call_recording', 'permission_lof', 'permission_loc', 'permission_ddf',
        'permission_ddc', 'permission_ddi', 'permission_srv',
    ];
    private const LIMITS = [
        'extension_number' => 40, 'registration_password' => 4096, 'ip_address' => 45,
        'user_name' => 160, 'department' => 160, 'notes' => 2000,
    ];

    public static function headers(): array
    {
        return ['Ramal', 'Senha', 'IP', 'Usuário', 'Departamento', 'Gravação', 'LOF', 'LOC', 'DDF', 'DDC', 'DDI', 'SRV', 'Status', 'Observações'];
    }

    public static function parse(string $content): array
    {
        if ($content === '' || strlen($content) > 2097152 || str_contains($content, "\0") || preg_match('//u', $content) !== 1) {
            throw new InvalidArgumentException('Envie um CSV UTF-8 válido de até 2 MiB.');
        }
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        $stream = fopen('php://memory', 'w+');
        if ($stream === false) {
            throw new InvalidArgumentException('Não foi possível ler o CSV.');
        }
        try {
            fwrite($stream, $content);
            $columns = null;
            $separator = ';';
            foreach ([';', ','] as $candidate) {
                rewind($stream);
                $header = fgetcsv($stream, 0, $candidate, '"', '');
                if ($header === false) continue;
                $mapped = [];
                foreach ($header as $cell) {
                    $key = self::normalize((string) $cell);
                    $mapped[] = self::HEADERS[$key] ?? (in_array($key, self::HEADERS, true) ? $key : null);
                }
                if (!in_array(null, $mapped, true) && in_array('extension_number', $mapped, true) && count($mapped) === count(array_unique($mapped))) {
                    $columns = $mapped;
                    $separator = $candidate;
                    break;
                }
            }
            if ($columns === null) {
                throw new InvalidArgumentException('Cabeçalho inválido ou duplicado. Use o modelo com a coluna Ramal.');
            }
            $rows = [];
            $seen = [];
            $record = 1;
            while (($cells = fgetcsv($stream, 0, $separator, '"', '')) !== false) {
                $record++;
                if ($cells === [null]) continue;
                if (count($cells) !== count($columns)) self::invalid($record, 'quantidade de colunas');
                if (count($rows) >= 1000) throw new InvalidArgumentException('O lote deve ter no máximo 1.000 ramais.');
                $row = array_fill_keys(array_values(self::HEADERS), '');
                foreach ($columns as $index => $field) {
                    $raw = (string) $cells[$index];
                    $row[$field] = $field === 'registration_password' ? $raw : trim($raw);
                }
                if ($row['extension_number'] === '') self::invalid($record, 'Ramal obrigatório');
                foreach (self::LIMITS as $field => $limit) {
                    if (strlen($row[$field]) > $limit) self::invalid($record, 'limite de tamanho de campo');
                }
                if ($row['ip_address'] !== '' && filter_var($row['ip_address'], FILTER_VALIDATE_IP) === false) self::invalid($record, 'IP inválido');
                foreach (self::FLAGS as $field) {
                    $flag = self::normalize($row[$field]);
                    if (!in_array($flag, ['', '0', '1', 'sim', 'nao'], true)) self::invalid($record, 'marcação inválida; use Sim, Não, 1 ou 0');
                    $row[$field] = in_array($flag, ['1', 'sim'], true) ? 1 : 0;
                }
                $row['status'] = $row['status'] === '' ? 'ativo' : self::normalize($row['status']);
                if (!in_array($row['status'], ['ativo', 'inativo', 'manutencao'], true)) self::invalid($record, 'Status inválido');
                $key = 'ramal:' . $row['extension_number'];
                if (isset($seen[$key])) self::invalid($record, 'Ramal duplicado no arquivo');
                $seen[$key] = true;
                $rows[] = $row;
            }
            if ($rows === []) throw new InvalidArgumentException('O CSV não contém ramais.');
            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private static function normalize(string $value): string
    {
        return strtolower(strtr(trim($value), [
            'Á' => 'a', 'á' => 'a', 'Ã' => 'a', 'ã' => 'a', 'Â' => 'a', 'â' => 'a', 'À' => 'a', 'à' => 'a',
            'É' => 'e', 'é' => 'e', 'Ê' => 'e', 'ê' => 'e', 'Í' => 'i', 'í' => 'i',
            'Ó' => 'o', 'ó' => 'o', 'Õ' => 'o', 'õ' => 'o', 'Ô' => 'o', 'ô' => 'o',
            'Ú' => 'u', 'ú' => 'u', 'Ç' => 'c', 'ç' => 'c',
        ]));
    }

    private static function invalid(int $record, string $reason): never
    {
        throw new InvalidArgumentException('Registro CSV ' . $record . ': ' . $reason . '.');
    }
}
