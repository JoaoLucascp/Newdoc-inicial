<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use DateTimeImmutable;
use NewDoc\Audit;
use NewDoc\Auth;
use NewDoc\CredentialCipher;
use NewDoc\Flash;
use NewDoc\Response;
use NewDoc\View;
use PDO;
use PDOException;
use RuntimeException;

final class NewbotController
{
    private const PAGE_SIZE = 15;
    private const INSTANCE_STATUSES = ['ativo', 'inativo', 'manutencao'];
    private const CHILD_SECTIONS = [
        'channels' => [
            'table' => 'newbot_channels',
            'label' => 'Canais',
            'summary_field' => 'channel_name',
            'fields' => [
                'external_id' => ['label' => 'ID externo', 'type' => 'text', 'max' => 160],
                'channel_name' => ['label' => 'Nome do canal', 'type' => 'text', 'max' => 160],
                'activation_date' => ['label' => 'Data de ativação', 'type' => 'date'],
                'activation_time' => ['label' => 'Hora de ativação', 'type' => 'time'],
                'plan' => ['label' => 'Plano', 'type' => 'text', 'max' => 160],
                'quantity' => ['label' => 'Quantidade', 'type' => 'number', 'min' => 0],
                'link' => ['label' => 'Link de acesso', 'type' => 'text', 'max' => 500],
                'status' => ['label' => 'Status', 'type' => 'select', 'default' => 'ativo', 'options' => self::INSTANCE_STATUSES],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
        'administrators' => [
            'table' => 'newbot_administrators',
            'label' => 'Administradores',
            'summary_field' => 'login',
            'fields' => [
                'login' => ['label' => 'Login', 'type' => 'text', 'max' => 160],
                'name' => ['label' => 'Nome', 'type' => 'text', 'max' => 160],
                'email' => ['label' => 'E-mail', 'type' => 'email', 'max' => 254],
                'password' => ['label' => 'Senha', 'type' => 'secret', 'column' => 'password_encrypted'],
                'supervisor_login' => ['label' => 'Login do supervisor', 'type' => 'text', 'max' => 160],
                'supervisor_email' => ['label' => 'E-mail do supervisor', 'type' => 'email', 'max' => 254],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
        'responsibles' => [
            'table' => 'newbot_responsibles',
            'label' => 'Responsáveis',
            'summary_field' => 'name',
            'fields' => [
                'name' => ['label' => 'Nome', 'type' => 'text', 'max' => 160],
                'contact' => ['label' => 'Contato', 'type' => 'text', 'max' => 160],
                'email' => ['label' => 'E-mail', 'type' => 'email', 'max' => 254],
                'department' => ['label' => 'Setor', 'type' => 'text', 'max' => 160],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
        'authentications' => [
            'table' => 'newbot_authentications',
            'label' => 'Autenticações',
            'summary_field' => 'platform',
            'fields' => [
                'platform' => [
                    'label' => 'Plataforma',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['facebook', 'instagram', 'whatsapp', 'site', 'outro'],
                ],
                'status' => ['label' => 'Status', 'type' => 'select', 'default' => 'pendente', 'options' => ['ativo', 'inativo', 'pendente']],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
        'integrations' => [
            'table' => 'newbot_integrations',
            'label' => 'Integrações',
            'summary_field' => 'name',
            'fields' => [
                'name' => ['label' => 'Nome da integração', 'type' => 'text', 'required' => true, 'max' => 160],
                'username' => ['label' => 'Usuário ou login', 'type' => 'text', 'max' => 160],
                'email' => ['label' => 'E-mail', 'type' => 'email', 'max' => 254],
                'password' => ['label' => 'Senha', 'type' => 'secret', 'column' => 'password_encrypted'],
                'link' => ['label' => 'Link', 'type' => 'text', 'max' => 500],
                'activation_date' => ['label' => 'Data de ativação', 'type' => 'date'],
                'status' => ['label' => 'Status', 'type' => 'select', 'default' => 'pendente', 'options' => ['ativo', 'inativo', 'pendente']],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
        'attendants' => [
            'table' => 'newbot_attendants',
            'label' => 'Atendentes',
            'summary_field' => 'name',
            'fields' => [
                'name' => ['label' => 'Nome', 'type' => 'text', 'max' => 160],
                'username' => ['label' => 'Usuário', 'type' => 'text', 'max' => 160],
                'email' => ['label' => 'E-mail', 'type' => 'email', 'max' => 254],
                'department' => ['label' => 'Setor', 'type' => 'text', 'max' => 160],
                'attendant_type' => ['label' => 'Tipo', 'type' => 'text', 'max' => 120],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
        'supervisors' => [
            'table' => 'newbot_supervisors',
            'label' => 'Supervisores',
            'summary_field' => 'name',
            'fields' => [
                'name' => ['label' => 'Nome', 'type' => 'text', 'max' => 160],
                'login' => ['label' => 'Login', 'type' => 'text', 'max' => 160],
                'email' => ['label' => 'E-mail', 'type' => 'email', 'max' => 254],
                'password' => ['label' => 'Senha', 'type' => 'secret', 'column' => 'password_encrypted'],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(): Response
    {
        $queryInput = $_GET['q'] ?? '';
        $query = is_string($queryInput) ? trim(substr($queryInput, 0, 160)) : '';
        $where = '';
        $parameters = [];
        if ($query !== '') {
            $where = ' WHERE (i.instance_name LIKE :query1 ESCAPE \'\\\' OR c.name LIKE :query2 ESCAPE \'\\\')';
            $needle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
            $parameters = ['query1' => $needle, 'query2' => $needle];
        }
        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM newbot_instances i JOIN companies c ON c.id = i.company_id' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
        $statement = $this->pdo->prepare(
            'SELECT i.id, i.company_id, i.instance_name, i.status, i.updated_at, c.name AS company_name
             FROM newbot_instances i JOIN companies c ON c.id = i.company_id' . $where . '
             ORDER BY c.name COLLATE NOCASE, i.instance_name COLLATE NOCASE, i.id DESC
             LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE)
        );
        $statement->execute($parameters);

        return View::render('newbot/index', [
            'instances' => $statement->fetchAll(),
            'query' => $query,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'canManage' => Auth::canManage(),
        ]);
    }

    public function company(int $companyId): Response
    {
        $company = $this->findCompany($companyId);
        if ($company === null) {
            return View::render('errors/404', [], 404);
        }
        $statement = $this->pdo->prepare(
            "SELECT id, instance_name, status, updated_at FROM newbot_instances
             WHERE company_id = :company_id
             ORDER BY CASE status WHEN 'ativo' THEN 0 ELSE 1 END, updated_at DESC, id DESC"
        );
        $statement->execute(['company_id' => $companyId]);
        Audit::record($this->pdo, 'consulta', 'newbot_instances', null, null, $companyId);

        return View::render('newbot/company', [
            'company' => $company,
            'instances' => $statement->fetchAll(),
            'canManage' => Auth::canManage(),
        ]);
    }

    public function create(int $companyId): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }
        $company = $this->findCompany($companyId);
        if ($company === null) {
            return View::render('errors/404', [], 404);
        }

        return $this->renderInstanceForm($company, [], [], 'create');
    }

    public function store(int $companyId): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }
        $company = $this->findCompany($companyId);
        if ($company === null) {
            return View::render('errors/404', [], 404);
        }
        [$data, $errors] = $this->validatedInstanceInput($companyId);
        if ($errors !== []) {
            return $this->renderInstanceForm($company, $data, $errors, 'create', 422);
        }
        $now = date('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO newbot_instances
                    (company_id, instance_name, instance_code, access_link, status, notes, source_document_id, created_at, updated_at, created_by, updated_by)
                 VALUES
                    (:company_id, :instance_name, :instance_code, :access_link, :status, :notes, :source_document_id, :created_at, :updated_at, :created_by, :updated_by)'
            );
            $statement->execute($data + [
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => Auth::user()['id'],
                'updated_by' => Auth::user()['id'],
            ]);
            $id = (int) $this->pdo->lastInsertId();
            Audit::record($this->pdo, 'criacao', 'newbot_instances', $id, 'record', $companyId);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($this->isActiveInstanceConflict($exception)) {
                return $this->renderInstanceForm(
                    $company,
                    $data,
                    ['status' => 'Esta empresa já possui uma instância Newbot ativa.'],
                    'create',
                    422
                );
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        Flash::success('Instância Newbot cadastrada com segurança.');

        return Response::redirect('/newbot/' . $id);
    }

    public function show(
        int $id,
        array $errors = [],
        int $statusCode = 200,
        bool $recordConsultation = true,
        array $formState = []
    ): Response {
        $instance = $this->findInstanceWithCompany($id);
        if ($instance === null) {
            return View::render('errors/404', [], 404);
        }
        $canManage = Auth::canManage();
        $sections = $this->loadChildSections($id, $canManage);
        $auditEvents = [];
        if ($canManage) {
            $audit = $this->pdo->prepare(
                'SELECT action, table_name, record_id, field_name, created_at
                 FROM audit_logs WHERE company_id = :company_id ORDER BY created_at DESC, id DESC LIMIT 10'
            );
            $audit->execute(['company_id' => $instance['company_id']]);
            $auditEvents = $audit->fetchAll();
        }
        if ($recordConsultation) {
            Audit::record($this->pdo, 'consulta', 'newbot_instances', $id, null, (int) $instance['company_id']);
        }

        return View::render('newbot/show', [
            'instance' => $instance,
            'sections' => $sections,
            'auditEvents' => $auditEvents,
            'errors' => $errors,
            'formState' => $formState,
            'canManage' => $canManage,
        ], $statusCode);
    }

    public function edit(int $id): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }
        $instance = $this->findInstanceWithCompany($id);
        if ($instance === null) {
            return View::render('errors/404', [], 404);
        }

        return $this->renderInstanceForm(
            ['id' => $instance['company_id'], 'name' => $instance['company_name']],
            $instance,
            [],
            'edit'
        );
    }

    public function update(int $id): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }
        $existing = $this->findInstanceWithCompany($id);
        if ($existing === null) {
            return View::render('errors/404', [], 404);
        }
        $company = ['id' => $existing['company_id'], 'name' => $existing['company_name']];
        [$data, $errors] = $this->validatedInstanceInput((int) $existing['company_id']);
        if ($errors !== []) {
            return $this->renderInstanceForm($company, $data + ['id' => $id], $errors, 'edit', 422);
        }
        $now = date('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'UPDATE newbot_instances SET instance_name = :instance_name, instance_code = :instance_code, access_link = :access_link, status = :status,
                 notes = :notes, source_document_id = :source_document_id,
                 updated_at = :updated_at, updated_by = :updated_by WHERE id = :id'
            );
            $statement->execute($data + [
                'id' => $id,
                'updated_at' => $now,
                'updated_by' => Auth::user()['id'],
            ]);
            foreach ($data as $field => $value) {
                if (($existing[$field] ?? null) !== $value) {
                    Audit::record($this->pdo, 'alteracao', 'newbot_instances', $id, $field, (int) $existing['company_id']);
                }
            }
            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($this->isActiveInstanceConflict($exception)) {
                return $this->renderInstanceForm(
                    $company,
                    $data + ['id' => $id],
                    ['status' => 'Esta empresa já possui outra instância Newbot ativa.'],
                    'edit',
                    422
                );
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        Flash::success('Instância Newbot atualizada.');

        return Response::redirect('/newbot/' . $id);
    }

    public function saveChild(int $instanceId): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }
        $instance = $this->findInstance($instanceId);
        if ($instance === null) {
            return View::render('errors/404', [], 404);
        }
        $typeInput = $_POST['section'] ?? '';
        $type = is_string($typeInput) ? $typeInput : '';
        if (!isset(self::CHILD_SECTIONS[$type])) {
            return View::render('errors/400', [], 400);
        }
        $recordInput = $_POST['record_id'] ?? '';
        if (!is_string($recordInput)) {
            return View::render('errors/400', [], 400);
        }
        $recordId = $recordInput === '' ? null : filter_var($recordInput, FILTER_VALIDATE_INT);
        if ($recordInput !== '' && ($recordId === false || $recordId < 1)) {
            return View::render('errors/400', [], 400);
        }
        $definition = self::CHILD_SECTIONS[$type];
        $existing = null;
        if ($recordId !== null) {
            $statement = $this->pdo->prepare(
                'SELECT * FROM ' . $definition['table'] . ' WHERE id = :id AND newbot_instance_id = :instance_id'
            );
            $statement->execute(['id' => $recordId, 'instance_id' => $instanceId]);
            $existing = $statement->fetch();
            if (!$existing) {
                return View::render('errors/404', [], 404);
            }
        }
        [$data, $errors, $values] = $this->validatedChildInput($definition, $existing);
        if ($errors !== []) {
            return $this->show(
                $instanceId,
                array_values($errors),
                422,
                false,
                ['section' => $type, 'record_id' => $recordId === false ? null : $recordId, 'values' => $values]
            );
        }
        $now = date('Y-m-d H:i:s');
        $companyId = (int) $instance['company_id'];
        $this->pdo->beginTransaction();
        try {
            if ($existing === null) {
                $columns = array_merge(array_keys($data), ['newbot_instance_id', 'created_at', 'updated_at']);
                $statement = $this->pdo->prepare(
                    'INSERT INTO ' . $definition['table'] . ' (' . implode(', ', $columns) . ')
                     VALUES (' . implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns)) . ')'
                );
                $statement->execute($data + [
                    'newbot_instance_id' => $instanceId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $recordId = (int) $this->pdo->lastInsertId();
                Audit::record($this->pdo, 'criacao', $definition['table'], $recordId, 'record', $companyId);
                $this->auditChildSecrets($definition, $data, $recordId, $companyId, 'criacao');
            } else {
                $assignments = array_map(static fn (string $column): string => $column . ' = :' . $column, array_keys($data));
                $assignments[] = 'updated_at = :updated_at';
                $statement = $this->pdo->prepare(
                    'UPDATE ' . $definition['table'] . ' SET ' . implode(', ', $assignments)
                    . ' WHERE id = :id AND newbot_instance_id = :newbot_instance_id'
                );
                $statement->execute($data + [
                    'id' => $recordId,
                    'newbot_instance_id' => $instanceId,
                    'updated_at' => $now,
                ]);
                foreach ($data as $column => $value) {
                    if (($existing[$column] ?? null) !== $value) {
                        Audit::record(
                            $this->pdo,
                            'alteracao',
                            $definition['table'],
                            (int) $recordId,
                            $this->childFieldName($definition, $column),
                            $companyId,
                            $this->isChildSecret($definition, $column)
                        );
                    }
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        Flash::success($definition['label'] . ' atualizado(s) com sucesso.');

        return Response::redirect('/newbot/' . $instanceId);
    }

    public function deleteChild(int $instanceId): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }
        $instance = $this->findInstance($instanceId);
        if ($instance === null) {
            return View::render('errors/404', [], 404);
        }
        $typeInput = $_POST['section'] ?? '';
        $type = is_string($typeInput) ? $typeInput : '';
        if (!isset(self::CHILD_SECTIONS[$type])) {
            return View::render('errors/400', [], 400);
        }
        $recordInput = $_POST['record_id'] ?? '';
        if (!is_string($recordInput)) {
            return View::render('errors/400', [], 400);
        }
        $recordId = filter_var($recordInput, FILTER_VALIDATE_INT);
        if ($recordId === false || $recordId < 1) {
            return View::render('errors/400', [], 400);
        }
        $definition = self::CHILD_SECTIONS[$type];
        $statement = $this->pdo->prepare(
            'SELECT * FROM ' . $definition['table'] . ' WHERE id = :id AND newbot_instance_id = :instance_id'
        );
        $statement->execute(['id' => $recordId, 'instance_id' => $instanceId]);
        $existing = $statement->fetch();
        if (!$existing) {
            return View::render('errors/404', [], 404);
        }
        $companyId = (int) $instance['company_id'];
        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare(
                'DELETE FROM ' . $definition['table'] . ' WHERE id = :id AND newbot_instance_id = :instance_id'
            );
            $delete->execute(['id' => $recordId, 'instance_id' => $instanceId]);
            $sensitive = false;
            foreach ($definition['fields'] as $field) {
                if ($field['type'] === 'secret') {
                    $sensitive = true;
                    break;
                }
            }
            Audit::record(
                $this->pdo,
                'exclusao',
                $definition['table'],
                $recordId,
                'record',
                $companyId,
                $sensitive
            );
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        Flash::success($definition['label'] . ' excluído(s) com sucesso.');

        return Response::redirect('/newbot/' . $instanceId);
    }
    private function renderInstanceForm(array $company, array $instance, array $errors, string $mode, int $status = 200): Response
    {
        return View::render('newbot/form', [
            'company' => $company,
            'instance' => $instance,
            'documents' => $this->newbotDocuments((int) $company['id'], (int) ($instance['source_document_id'] ?? 0)),
            'errors' => $errors,
            'mode' => $mode,
            'canManage' => Auth::canManage(),
        ], $status);
    }

    private function validatedInstanceInput(int $companyId): array
    {
        $errors = [];
        $data = [];
        foreach (['instance_name' => 160, 'instance_code' => 160, 'access_link' => 500, 'notes' => 2000] as $field => $max) {
            $raw = $_POST[$field] ?? '';
            if (!is_string($raw)) {
                $errors[$field] = 'Informe um valor válido.';
                $raw = '';
            }
            $value = trim($raw);
            if (strlen($value) > $max) {
                $errors[$field] = 'O campo excede o limite permitido.';
            }
            $data[$field] = $value === '' ? null : $value;
        }
        if ($data['access_link'] !== null && !isset($errors['access_link']) && preg_match('#^https?://[^\s]+$#i', $data['access_link']) !== 1) {
            $errors['access_link'] = 'Informe um link iniciado por http:// ou https://.';
        }
        $statusInput = $_POST['status'] ?? 'ativo';
        $status = is_string($statusInput) ? $statusInput : '';
        if (!in_array($status, self::INSTANCE_STATUSES, true)) {
            $errors['status'] = 'Selecione um status válido.';
            $status = 'ativo';
        }
        $data['status'] = $status;
        $sourceInput = $_POST['source_document_id'] ?? '';
        if (!is_string($sourceInput)) {
            $errors['source_document_id'] = 'Selecione um documento Newbot válido.';
            $sourceInput = '';
        }
        $sourceId = $sourceInput === '' ? null : filter_var($sourceInput, FILTER_VALIDATE_INT);
        if ($sourceInput !== '' && ($sourceId === false || $sourceId < 1)) {
            $errors['source_document_id'] = 'Selecione um documento Newbot válido.';
        } elseif ($sourceId !== null) {
            $statement = $this->pdo->prepare('SELECT company_id FROM documents WHERE id = :id AND document_type = \'newbot\'');
            $statement->execute(['id' => $sourceId]);
            $documentCompanyId = $statement->fetchColumn();
            if ($documentCompanyId === false || ($documentCompanyId !== null && (int) $documentCompanyId !== $companyId)) {
                $errors['source_document_id'] = 'O documento não pertence a esta empresa ou não é do tipo Newbot.';
            }
        }
        $data['source_document_id'] = $sourceId === false ? null : $sourceId;

        return [$data, $errors];
    }

    private function validatedChildInput(array $definition, ?array $existing): array
    {
        $data = [];
        $errors = [];
        $values = [];
        $secretInputs = [];
        foreach ($definition['fields'] as $name => $field) {
            $type = $field['type'];
            $column = $field['column'] ?? $name;
            if ($type === 'secret') {
                $raw = $_POST[$name] ?? '';
                unset($_POST[$name]);
                if (!is_string($raw)) {
                    $errors[$name] = 'Informe um valor válido.';
                    $raw = '';
                }
                $clear = ($_POST['clear_' . $name] ?? '') === '1';
                if ($raw !== '') {
                    $secretInputs[$name] = $raw;
                }
                if ($raw !== '' && $clear) {
                    $errors[$name] = 'Escolha entre substituir ou remover a credencial.';
                } elseif ($clear) {
                    $data[$column] = null;
                } else {
                    $data[$column] = $existing[$column] ?? null;
                }
                $values['clear_' . $name] = $clear ? '1' : '';
                continue;
            }
            $raw = $_POST[$name] ?? '';
            if (!is_string($raw)) {
                $errors[$name] = 'Informe um valor válido.';
                $raw = '';
            }
            $value = trim($raw);
            if ($type === 'select') {
                $options = $field['options'];
                if ($value === '' && !empty($field['required'])) {
                    $errors[$name] = 'Selecione uma opção válida.';
                } elseif ($value !== '' && !in_array($value, $options, true)) {
                    $errors[$name] = 'Selecione uma opção válida.';
                }
                $data[$column] = $value === '' ? ($field['default'] ?? null) : $value;
            } elseif ($type === 'number') {
                $number = $value === '' ? null : filter_var($value, FILTER_VALIDATE_INT);
                if ($number === false || ($number !== null && $number < ($field['min'] ?? 0))) {
                    $errors[$name] = 'Informe um número inteiro válido.';
                }
                $data[$column] = $number === false ? null : $number;
            } elseif ($type === 'date') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if ($value !== '' && ($date === false || $date->format('Y-m-d') !== $value)) {
                    $errors[$name] = 'Informe uma data válida.';
                }
                $data[$column] = $value === '' ? null : $value;
            } elseif ($type === 'time') {
                $time = DateTimeImmutable::createFromFormat('H:i', $value);
                if ($value !== '' && ($time === false || $time->format('H:i') !== $value)) {
                    $errors[$name] = 'Informe uma hora válida no formato HH:MM.';
                }
                $data[$column] = $value === '' ? null : $value;
            } else {
                if (!empty($field['required']) && $value === '') {
                    $errors[$name] = 'Este campo é obrigatório.';
                } elseif (strlen($value) > ($field['max'] ?? 500)) {
                    $errors[$name] = 'O campo excede o limite permitido.';
                } elseif ($type === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[$name] = 'Informe um e-mail válido.';
                }
                $data[$column] = $value === '' ? null : $value;
            }
            $values[$name] = $value;
        }
        if ($secretInputs !== []) {
            try {
                CredentialCipher::assertAvailable();
            } catch (RuntimeException) {
                foreach (array_keys($secretInputs) as $name) {
                    $errors[$name] = 'A criptografia não está configurada. Solicite a configuração segura de CREDENTIALS_KEY.';
                }
            }
            if ($errors === []) {
                foreach ($secretInputs as $name => $secret) {
                    $data[$definition['fields'][$name]['column']] = CredentialCipher::encrypt($secret);
                }
            }
            foreach ($secretInputs as &$secret) {
                if (function_exists('sodium_memzero')) {
                    sodium_memzero($secret);
                }
            }
            unset($secret);
        }

        return [$data, $errors, $values];
    }

    private function loadChildSections(int $instanceId, bool $includeRecords): array
    {
        $sections = [];
        foreach (self::CHILD_SECTIONS as $type => $definition) {
            $statement = $this->pdo->prepare(
                'SELECT * FROM ' . $definition['table'] . ' WHERE newbot_instance_id = :instance_id ORDER BY id DESC'
            );
            $statement->execute(['instance_id' => $instanceId]);
            $rows = $statement->fetchAll();
            $records = [];
            if ($includeRecords) {
                foreach ($rows as $row) {
                    $secrets = [];
                    foreach ($definition['fields'] as $name => $field) {
                        if ($field['type'] === 'secret') {
                            $column = $field['column'];
                            $secrets[$name] = !empty($row[$column]);
                            unset($row[$column]);
                        }
                    }
                    $records[] = ['values' => $row, 'secret_fields' => $secrets];
                }
            }
            $sections[] = [
                'type' => $type,
                'label' => $definition['label'],
                'summary_field' => $definition['summary_field'],
                'fields' => $includeRecords ? array_map(
                    static fn (string $name, array $field): array => ['name' => $name] + $field,
                    array_keys($definition['fields']),
                    array_values($definition['fields'])
                ) : [],
                'records' => $records,
                'record_count' => count($rows),
            ];
        }

        return $sections;
    }

    private function auditChildSecrets(array $definition, array $data, int $recordId, int $companyId, string $action): void
    {
        foreach ($data as $column => $value) {
            if ($value !== null && $this->isChildSecret($definition, $column)) {
                Audit::record($this->pdo, $action, $definition['table'], $recordId, $this->childFieldName($definition, $column), $companyId, true);
            }
        }
    }

    private function childFieldName(array $definition, string $column): string
    {
        foreach ($definition['fields'] as $name => $field) {
            if (($field['column'] ?? $name) === $column) {
                return $name;
            }
        }

        return $column;
    }

    private function isChildSecret(array $definition, string $column): bool
    {
        foreach ($definition['fields'] as $name => $field) {
            if ($field['type'] === 'secret' && ($field['column'] ?? $name) === $column) {
                return true;
            }
        }

        return false;
    }

    private function findCompany(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name FROM companies WHERE id = :id');
        $statement->execute(['id' => $id]);
        $company = $statement->fetch();

        return $company ?: null;
    }

    private function findInstance(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM newbot_instances WHERE id = :id');
        $statement->execute(['id' => $id]);
        $instance = $statement->fetch();

        return $instance ?: null;
    }

    private function findInstanceWithCompany(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT i.*, c.name AS company_name
             FROM newbot_instances i JOIN companies c ON c.id = i.company_id WHERE i.id = :id'
        );
        $statement->execute(['id' => $id]);
        $instance = $statement->fetch();

        return $instance ?: null;
    }

    private function newbotDocuments(int $companyId, int $selectedId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, original_filename, company_id FROM documents
             WHERE document_type = \'newbot\' AND (company_id = :company_id OR company_id IS NULL OR id = :selected_id)
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['company_id' => $companyId, 'selected_id' => $selectedId]);

        return $statement->fetchAll();
    }

    private function isActiveInstanceConflict(PDOException $exception): bool
    {
        return str_contains($exception->getMessage(), 'UNIQUE constraint failed: newbot_instances.company_id');
    }
}