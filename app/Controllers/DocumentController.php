<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use NewDoc\Audit;
use NewDoc\Auth;
use NewDoc\Config;
use NewDoc\Flash;
use NewDoc\Response;
use NewDoc\View;
use PDO;
use PDOException;
use RuntimeException;

final class DocumentController
{
    private const PAGE_SIZE = 15;
    private const TYPES = ['newsic', 'newbot', 'newvoz', 'contatos', 'outro'];
    private const STATUSES = ['pendente', 'em_analise', 'validado', 'historico', 'descartado'];
    private const MIME_TYPES = [
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(): Response
    {
        $status = (string) ($_GET['status'] ?? '');
        $type = (string) ($_GET['type'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        if (!in_array($type, self::TYPES, true)) {
            $type = '';
        }
        $where = [];
        $parameters = [];
        if ($status !== '') {
            $where[] = 'd.source_status = :status';
            $parameters['status'] = $status;
        }
        if ($type !== '') {
            $where[] = 'd.document_type = :type';
            $parameters['type'] = $type;
        }
        if (isset($_GET['company_id']) && ctype_digit((string) $_GET['company_id'])) {
            $where[] = 'd.company_id = :company_id';
            $parameters['company_id'] = (int) $_GET['company_id'];
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM documents d' . $whereSql);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;
        $statement = $this->pdo->prepare(
            'SELECT d.id, d.company_id, d.original_filename, d.document_type, d.source_status,
                    d.is_current, d.file_size_bytes, d.file_hash_sha256, d.created_at, c.name AS company_name
             FROM documents d LEFT JOIN companies c ON c.id = d.company_id'
            . $whereSql . '
             ORDER BY d.created_at DESC, d.id DESC
             LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset
        );
        $statement->execute($parameters);
        $companies = $this->pdo->query('SELECT id, name FROM companies ORDER BY name COLLATE NOCASE, id')->fetchAll();

        return View::render('documents/index', [
            'documents' => $statement->fetchAll(),
            'companies' => $companies,
            'status' => $status,
            'type' => $type,
            'companyId' => (string) ($_GET['company_id'] ?? ''),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'canManage' => Auth::canManage(),
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
        ]);
    }

    public function create(): Response
    {
        $companies = $this->pdo->query('SELECT id, name FROM companies ORDER BY name COLLATE NOCASE, id')->fetchAll();

        return View::render('documents/form', [
            'companies' => $companies,
            'errors' => [],
            'values' => [],
            'maxUploadBytes' => Config::int('MAX_UPLOAD_BYTES', 20971520),
        ]);
    }

    public function store(): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }

        $companies = $this->pdo->query('SELECT id, name FROM companies ORDER BY name COLLATE NOCASE, id')->fetchAll();
        $errors = [];
        $values = [
            'company_id' => trim((string) ($_POST['company_id'] ?? '')),
            'document_type' => (string) ($_POST['document_type'] ?? ''),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];
        $companyId = $values['company_id'] === '' ? null : filter_var($values['company_id'], FILTER_VALIDATE_INT);
        if ($values['company_id'] !== '' && $companyId === false) {
            $errors['company_id'] = 'Selecione uma empresa válida.';
        }
        if ($companyId !== null && $companyId !== false) {
            $checkCompany = $this->pdo->prepare('SELECT name FROM companies WHERE id = :id');
            $checkCompany->execute(['id' => $companyId]);
            $companyName = $checkCompany->fetchColumn();
            if ($companyName === false) {
                $errors['company_id'] = 'A empresa selecionada não foi encontrada.';
            } else {
                $duplicates = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM companies WHERE name = :name COLLATE NOCASE'
                );
                $duplicates->execute(['name' => $companyName]);
                if ((int) $duplicates->fetchColumn() > 1
                    && ($_POST['confirm_company_link'] ?? '') !== '1'
                ) {
                    $errors['confirm_company_link'] = 'Há empresas com o mesmo nome. Confirme o vínculo pelo ID selecionado.';
                }
            }
        }
        if (!in_array($values['document_type'], self::TYPES, true)) {
            $errors['document_type'] = 'Selecione um tipo de documento válido.';
        }
        if (strlen($values['notes']) > 2000) {
            $errors['notes'] = 'As observações devem ter até 2.000 caracteres.';
        }
        $file = $_FILES['document'] ?? null;
        $maxBytes = Config::int('MAX_UPLOAD_BYTES', 20971520);
        if (!is_array($file) || !isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            $errors['document'] = $this->uploadError($file);
        } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
            $errors['document'] = 'O arquivo enviado não pôde ser validado.';
        } elseif ((int) $file['size'] < 1 || (int) $file['size'] > $maxBytes) {
            $errors['document'] = 'O arquivo está vazio ou excede o limite configurado.';
        }

        $originalName = '';
        $extension = '';
        $sha256 = '';
        if ($errors === [] && is_array($file)) {
            $originalName = basename(str_replace('\\', '/', (string) $file['name']));
            if (strlen($originalName) > 255 || $originalName === '') {
                $errors['document'] = 'Nome de arquivo inválido.';
            }
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!isset(self::MIME_TYPES[$extension])) {
                $errors['document'] = 'Formato não permitido. Envie XLSX, PDF, DOCX, ODT ou CSV.';
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string) $file['tmp_name']);
            if ($extension !== '' && isset(self::MIME_TYPES[$extension])
                && !in_array($mime, self::MIME_TYPES[$extension], true)
            ) {
                $errors['document'] = 'O conteúdo do arquivo não corresponde ao formato informado.';
            }
            $sha256 = hash_file('sha256', (string) $file['tmp_name']) ?: '';
            if ($sha256 === '') {
                $errors['document'] = 'Não foi possível verificar a integridade do arquivo.';
            } else {
                $duplicate = $this->pdo->prepare(
                    'SELECT 1 FROM documents WHERE file_hash_sha256 = :hash'
                );
                $duplicate->execute(['hash' => $sha256]);
                if ($duplicate->fetchColumn()) {
                    $errors['document'] = 'Este arquivo já está registrado no acervo.';
                }
            }
        }

        if ($errors === []) {
            try {
                \NewDoc\CredentialCipher::assertAvailable();
            } catch (RuntimeException) {
                $errors['document'] = 'A criptografia não está configurada. Solicite a configuração segura de CREDENTIALS_KEY.';
            }
        }

        if ($errors !== []) {
            return View::render('documents/form', [
                'companies' => $companies,
                'errors' => $errors,
                'values' => $values,
                'maxUploadBytes' => $maxBytes,
            ], 422);
        }

        $storedFilename = bin2hex(random_bytes(24)) . '.enc';
        $storageDirectory = NEWDOC_ROOT . '/storage/documents';
        if (!is_dir($storageDirectory)
            && !mkdir($storageDirectory, 0700, true)
            && !is_dir($storageDirectory)
        ) {
            throw new RuntimeException('Não foi possível preparar o armazenamento de documentos.');
        }
        $destination = $storageDirectory . '/' . $storedFilename;
        $originalContent = file_get_contents((string) $file['tmp_name']);
        if ($originalContent === false) {
            throw new RuntimeException('Não foi possível ler o arquivo recebido.');
        }
        $encryptedContent = \NewDoc\CredentialCipher::encrypt($originalContent);
        unset($originalContent);
        $encryptedLength = strlen($encryptedContent);
        $writtenBytes = file_put_contents($destination, $encryptedContent, LOCK_EX);
        unset($encryptedContent);
        if ($writtenBytes === false || $writtenBytes !== $encryptedLength) {
            if (is_file($destination)) {
                unlink($destination);
            }
            throw new RuntimeException('Não foi possível armazenar o documento criptografado.');
        }

        $now = date('Y-m-d H:i:s');
        try {
            $this->pdo->beginTransaction();
            $insert = $this->pdo->prepare(
                'INSERT INTO documents
                    (company_id, original_filename, stored_filename, file_path, file_hash_sha256,
                     file_size_bytes, document_type, source_status, is_current, uploaded_by,
                     notes, created_at, updated_at)
                 VALUES
                    (:company_id, :original_filename, :stored_filename, :file_path, :file_hash_sha256,
                     :file_size_bytes, :document_type, :source_status, 0, :uploaded_by,
                     :notes, :created_at, :updated_at)'
            );
            $insert->execute([
                'company_id' => $companyId,
                'original_filename' => $originalName,
                'stored_filename' => $storedFilename,
                'file_path' => 'documents/' . $storedFilename,
                'file_hash_sha256' => $sha256,
                'file_size_bytes' => (int) $file['size'],
                'document_type' => $values['document_type'],
                'source_status' => 'pendente',
                'uploaded_by' => Auth::user()['id'],
                'notes' => $values['notes'] === '' ? null : $values['notes'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $documentId = (int) $this->pdo->lastInsertId();
            Audit::record(
                $this->pdo,
                'criacao',
                'documents',
                $documentId,
                'record',
                $companyId === false ? null : $companyId
            );
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (is_file($destination)) {
                unlink($destination);
            }
            if ($exception instanceof PDOException && str_contains($exception->getMessage(), 'UNIQUE')) {
                return View::render('documents/form', [
                    'companies' => $companies,
                    'errors' => ['document' => 'Este arquivo já está registrado no acervo.'],
                    'values' => $values,
                    'maxUploadBytes' => $maxBytes,
                ], 422);
            }

            throw $exception;
        }

        Flash::success('Documento registrado e armazenado criptografado, sem importação de dados.');

        return Response::redirect('/documentos');
    }

    public function updateStatus(int $id): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }
        $status = (string) ($_POST['source_status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            return View::render('errors/400', [], 400);
        }
        $existing = $this->pdo->prepare(
            'SELECT company_id, source_status FROM documents WHERE id = :id'
        );
        $existing->execute(['id' => $id]);
        $document = $existing->fetch();
        if (!$document) {
            return View::render('errors/404', [], 404);
        }

        if ($document['source_status'] !== $status) {
            $this->pdo->beginTransaction();
            try {
                $update = $this->pdo->prepare(
                    'UPDATE documents SET source_status = :status, updated_at = :updated_at WHERE id = :id'
                );
                $update->execute([
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $id,
                ]);
                Audit::record(
                    $this->pdo,
                    'alteracao',
                    'documents',
                    $id,
                    'source_status',
                    $document['company_id'] === null ? null : (int) $document['company_id']
                );
                $this->pdo->commit();
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $exception;
            }
            Flash::success('Status do documento atualizado. Nenhum dado foi importado.');
        }

        return Response::redirect('/documentos');
    }

    private function uploadError(mixed $file): string
    {
        if (!is_array($file) || !isset($file['error'])) {
            return 'Selecione um arquivo para registrar.';
        }

        return match ((int) $file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite permitido pelo servidor.',
            UPLOAD_ERR_PARTIAL => 'O envio do arquivo foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Selecione um arquivo para registrar.',
            default => 'Não foi possível receber o arquivo enviado.',
        };
    }
}
