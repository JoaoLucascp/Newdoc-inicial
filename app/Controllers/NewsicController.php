<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use NewDoc\Audit;
use NewDoc\Auth;
use NewDoc\CredentialCipher;
use NewDoc\Flash;
use NewDoc\Response;
use NewDoc\View;
use PDO;
use PDOException;
use RuntimeException;

final class NewsicController
{
    private const PAGE_SIZE = 15;
    private const INSTANCE_STATUSES = ['ativo', 'inativo', 'manutencao'];
    private const SERVER_TYPES = ['virtual', 'cloud', 'fisico'];
    private const SECRET_COLUMNS = [
        'web_password_encrypted' => 'web_password',
        'ssh_password_encrypted' => 'ssh_password',
    ];
    private const CHILD_SECTIONS = [
        'extensions' => [
            'table' => 'newsic_extensions',
            'label' => 'Ramais',
            'summary_field' => 'extension_number',
            'fields' => [
                'extension_number' => ['label' => 'Número do ramal', 'type' => 'text', 'required' => true, 'max' => 40],
                'registration_password' => [
                    'label' => 'Senha de registro',
                    'type' => 'secret',
                    'column' => 'registration_password_encrypted',
                ],
                'ip_address' => ['label' => 'IP do aparelho', 'type' => 'ip', 'max' => 45],
                'user_name' => ['label' => 'Usuário', 'type' => 'text', 'max' => 160],
                'department' => ['label' => 'Departamento', 'type' => 'text', 'max' => 160],
                'call_recording' => ['label' => 'Grava chamadas', 'type' => 'checkbox'],
                'permission_lof' => ['label' => 'Local para fixo', 'type' => 'checkbox'],
                'permission_loc' => ['label' => 'Local para celular', 'type' => 'checkbox'],
                'permission_ddf' => ['label' => 'DDD para fixo', 'type' => 'checkbox'],
                'permission_ddc' => ['label' => 'DDD para celular', 'type' => 'checkbox'],
                'permission_ddi' => ['label' => 'DDI', 'type' => 'checkbox'],
                'permission_srv' => ['label' => 'Serviços', 'type' => 'checkbox'],
                'status' => [
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'],
                    'default' => 'ativo',
                ],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
        'trunks' => [
            'table' => 'newsic_trunks',
            'label' => 'Troncos',
            'summary_field' => 'pilot_number',
            'fields' => [
                'pilot_number' => ['label' => 'Número piloto', 'type' => 'text', 'required' => true, 'max' => 80],
                'trunk_type' => [
                    'label' => 'Tipo',
                    'type' => 'select',
                    'options' => [
                        'analogico' => 'Analógico',
                        'sip' => 'SIP',
                        'sip_trunk' => 'SIP Trunk',
                        'voip' => 'VoIP',
                        'outro' => 'Outro',
                    ],
                ],
                'carrier' => ['label' => 'Operadora', 'type' => 'text', 'max' => 160],
                'receives_calls' => [
                    'label' => 'Recebe chamadas',
                    'type' => 'select',
                    'options' => ['1' => 'Sim', '0' => 'Não'],
                ],
                'channel_quantity' => ['label' => 'Quantidade de canais', 'type' => 'number', 'min' => 0],
                'ddrs' => ['label' => 'DDRs', 'type' => 'textarea', 'max' => 2000],
                'status' => [
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'manutencao' => 'Manutenção'],
                    'default' => 'ativo',
                ],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
        'ura_options' => [
            'table' => 'newsic_ura_options',
            'label' => 'Opções de URA',
            'summary_field' => 'option',
            'fields' => [
                'ura_type' => ['label' => 'Tipo de URA', 'type' => 'text', 'max' => 120],
                'option' => ['label' => 'Opção', 'type' => 'text', 'max' => 40],
                'schedule' => ['label' => 'Horário', 'type' => 'text', 'max' => 200],
                'recording' => ['label' => 'Gravação', 'type' => 'text', 'max' => 500],
                'destination' => ['label' => 'Destino', 'type' => 'text', 'max' => 500],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
        'routings' => [
            'table' => 'newsic_routings',
            'label' => 'Direcionamentos',
            'summary_field' => 'number',
            'fields' => [
                'ipo' => ['label' => 'IPO', 'type' => 'text', 'max' => 120],
                'number' => ['label' => 'Número', 'type' => 'text', 'max' => 80],
                'name' => ['label' => 'Nome', 'type' => 'text', 'max' => 160],
                'forwarding' => ['label' => 'Transbordo', 'type' => 'text', 'max' => 500],
                'recording' => ['label' => 'Gravação', 'type' => 'text', 'max' => 500],
                'destination' => ['label' => 'Destino', 'type' => 'text', 'max' => 500],
                'notes' => ['label' => 'Observações', 'type' => 'textarea', 'max' => 2000],
            ],
        ],
        'networks' => [
            'table' => 'newsic_networks',
            'label' => 'Redes',
            'summary_field' => 'network_type',
            'fields' => [
                'gateway' => ['label' => 'Gateway', 'type' => 'ip', 'max' => 45],
                'network_type' => ['label' => 'Tipo de rede', 'type' => 'text', 'max' => 120],
                'interface_ip' => ['label' => 'IP da interface', 'type' => 'ip', 'max' => 45],
                'subnet_mask' => ['label' => 'Máscara de sub-rede', 'type' => 'ip', 'max' => 45],
                'dns' => ['label' => 'DNS', 'type' => 'text', 'max' => 255],
                'proxy_ip' => ['label' => 'IP do proxy', 'type' => 'ip', 'max' => 45],
                'proxy_port' => ['label' => 'Porta do proxy', 'type' => 'text', 'max' => 20],
                'proxy_type' => ['label' => 'Tipo do proxy', 'type' => 'text', 'max' => 80],
                'audio_ip' => ['label' => 'IP de áudio', 'type' => 'ip', 'max' => 45],
                'audio_subnet_mask' => ['label' => 'Máscara de áudio', 'type' => 'ip', 'max' => 45],
                'audio_gateway' => ['label' => 'Gateway de áudio', 'type' => 'ip', 'max' => 45],
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
            $where = ' WHERE n.server_name LIKE :query1 ESCAPE \'\\\' OR c.name LIKE :query2 ESCAPE \'\\\'';
            $needle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
            $parameters = ['query1' => $needle, 'query2' => $needle];
        }

        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM newsic_instances n JOIN companies c ON c.id = n.company_id' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;
        $statement = $this->pdo->prepare(
            'SELECT n.id, n.company_id, n.server_name, n.server_type, n.status, n.updated_at,
                    c.name AS company_name
             FROM newsic_instances n JOIN companies c ON c.id = n.company_id'
            . $where . '
             ORDER BY c.name COLLATE NOCASE, n.server_name COLLATE NOCASE, n.id DESC
             LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset
        );
        $statement->execute($parameters);

        return View::render('newsic/index', [
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
            'SELECT id, server_name, server_type, status, software_version, updated_at
             FROM newsic_instances WHERE company_id = :company_id
             ORDER BY CASE status WHEN \'ativo\' THEN 0 ELSE 1 END, updated_at DESC, id DESC'
        );
        $statement->execute(['company_id' => $companyId]);
        Audit::record($this->pdo, 'consulta', 'newsic_instances', null, null, $companyId);

        return View::render('newsic/company', [
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

        [$data, $values, $errors] = $this->validatedInstanceInput($companyId, null);
        if ($errors !== []) {
            return $this->renderInstanceForm($company, $values, $errors, 'create', 422);
        }

        $now = date('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO newsic_instances
                (company_id, server_name, internal_ip, external_ip, web_port, web_username,
                 web_password_encrypted, ssh_port, ssh_username, ssh_password_encrypted,
                 server_type, freemyip_link, software_version, documentation_path, status,
                 notes, source_document_id, created_at, updated_at, created_by, updated_by)
             VALUES
                (:company_id, :server_name, :internal_ip, :external_ip, :web_port, :web_username,
                 :web_password_encrypted, :ssh_port, :ssh_username, :ssh_password_encrypted,
                 :server_type, :freemyip_link, :software_version, :documentation_path, :status,
                 :notes, :source_document_id, :created_at, :updated_at, :created_by, :updated_by)'
        );
        $this->pdo->beginTransaction();
        try {
            $statement->execute($data + [
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => Auth::user()['id'],
                'updated_by' => Auth::user()['id'],
            ]);
            $id = (int) $this->pdo->lastInsertId();
            Audit::record($this->pdo, 'criacao', 'newsic_instances', $id, 'record', $companyId);
            foreach (self::SECRET_COLUMNS as $column => $field) {
                if ($data[$column] !== null) {
                    Audit::record($this->pdo, 'criacao', 'newsic_instances', $id, $field, $companyId, true);
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
                    $values,
                    ['status' => 'Esta empresa já possui uma instância Newsic ativa.'],
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
        Flash::success('Instância Newsic cadastrada com segurança.');

        return Response::redirect('/newsic/' . $id);
    }

    public function show(
        int $id,
        array $errors = [],
        int $statusCode = 200,
        bool $recordConsultation = true,
        array $formState = []
    ): Response {
        $canManage = Auth::canManage();
        $columns = [
            'n.id',
            'n.company_id',
            'n.server_name',
            'n.server_type',
            'n.software_version',
            'n.status',
            'n.source_document_id',
            'n.created_at',
            'n.updated_at',
            'c.name AS company_name',
        ];
        if ($canManage) {
            $columns = array_merge($columns, [
                'n.internal_ip',
                'n.external_ip',
                'n.web_port',
                'n.web_username',
                'CASE WHEN n.web_password_encrypted IS NULL THEN 0 ELSE 1 END AS has_web_password',
                'n.ssh_port',
                'n.ssh_username',
                'CASE WHEN n.ssh_password_encrypted IS NULL THEN 0 ELSE 1 END AS has_ssh_password',
                'n.freemyip_link',
                'n.documentation_path',
                'n.notes',
            ]);
        }
        $statement = $this->pdo->prepare(
            'SELECT ' . implode(', ', $columns) . '
             FROM newsic_instances n JOIN companies c ON c.id = n.company_id
             WHERE n.id = :id'
        );
        $statement->execute(['id' => $id]);
        $instance = $statement->fetch();
        if (!$instance) {
            return View::render('errors/404', [], 404);
        }

        $companyId = (int) $instance['company_id'];
        $auditEvents = [];
        if ($canManage) {
            $auditStatement = $this->pdo->prepare(
                'SELECT action, table_name, record_id, field_name, created_at
                 FROM audit_logs WHERE company_id = :company_id
                 ORDER BY created_at DESC, id DESC LIMIT 10'
            );
            $auditStatement->execute(['company_id' => $companyId]);
            $auditEvents = $auditStatement->fetchAll();
        }
        $sections = $this->loadChildSections($id, $canManage);
        if ($recordConsultation) {
            Audit::record($this->pdo, 'consulta', 'newsic_instances', $id, null, $companyId);
        }

        return View::render('newsic/show', [
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
        $statement = $this->pdo->prepare(
            'SELECT n.id, n.company_id, n.server_name, n.internal_ip, n.external_ip,
                    n.web_port, n.web_username,
                    CASE WHEN n.web_password_encrypted IS NULL THEN 0 ELSE 1 END AS has_web_password,
                    n.ssh_port, n.ssh_username,
                    CASE WHEN n.ssh_password_encrypted IS NULL THEN 0 ELSE 1 END AS has_ssh_password,
                    n.server_type, n.freemyip_link, n.software_version, n.documentation_path,
                    n.status, n.notes, n.source_document_id, n.created_at, n.updated_at,
                    c.name AS company_name
             FROM newsic_instances n JOIN companies c ON c.id = n.company_id WHERE n.id = :id'
        );
        $statement->execute(['id' => $id]);
        $instance = $statement->fetch();
        if (!$instance) {
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
        $existing = $this->findInstance($id);
        if ($existing === null) {
            return View::render('errors/404', [], 404);
        }
        $company = $this->findCompany((int) $existing['company_id']);
        if ($company === null) {
            return View::render('errors/404', [], 404);
        }

        [$data, $values, $errors] = $this->validatedInstanceInput((int) $company['id'], $existing);
        if ($errors !== []) {
            return $this->renderInstanceForm($company, $values + ['id' => $id], $errors, 'edit', 422);
        }

        $now = date('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare(
                'UPDATE newsic_instances SET
                    server_name = :server_name, internal_ip = :internal_ip, external_ip = :external_ip,
                    web_port = :web_port, web_username = :web_username,
                    web_password_encrypted = :web_password_encrypted, ssh_port = :ssh_port,
                    ssh_username = :ssh_username, ssh_password_encrypted = :ssh_password_encrypted,
                    server_type = :server_type, freemyip_link = :freemyip_link,
                    software_version = :software_version, documentation_path = :documentation_path,
                    status = :status, notes = :notes, source_document_id = :source_document_id,
                    updated_at = :updated_at, updated_by = :updated_by
                 WHERE id = :id'
            );
            $update->execute($data + [
                'updated_at' => $now,
                'updated_by' => Auth::user()['id'],
                'id' => $id,
            ]);
            foreach ($data as $field => $value) {
                if (($existing[$field] ?? null) !== $value) {
                    $isSensitive = isset(self::SECRET_COLUMNS[$field]);
                    Audit::record(
                        $this->pdo,
                        'alteracao',
                        'newsic_instances',
                        $id,
                        self::SECRET_COLUMNS[$field] ?? $field,
                        (int) $company['id'],
                        $isSensitive
                    );
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
                    $values + ['id' => $id],
                    ['status' => 'Esta empresa já possui outra instância Newsic ativa.'],
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
        Flash::success('Instância Newsic atualizada.');

        return Response::redirect('/newsic/' . $id);
    }

    public function saveChild(int $instanceId): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }
        $instanceStatement = $this->pdo->prepare(
            'SELECT id, company_id FROM newsic_instances WHERE id = :id'
        );
        $instanceStatement->execute(['id' => $instanceId]);
        $instance = $instanceStatement->fetch();
        if (!$instance) {
            return View::render('errors/404', [], 404);
        }
        $typeInput = $_POST['section'] ?? '';
        $type = is_string($typeInput) ? $typeInput : '';
        if (!isset(self::CHILD_SECTIONS[$type])) {
            return View::render('errors/400', [], 400);
        }

        $definition = self::CHILD_SECTIONS[$type];
        $recordIdInput = $_POST['record_id'] ?? '';
        if (!is_string($recordIdInput)) {
            return View::render('errors/400', [], 400);
        }
        $recordId = $recordIdInput === '' ? null : filter_var($recordIdInput, FILTER_VALIDATE_INT);
        if ($recordIdInput !== '' && ($recordId === false || $recordId < 1)) {
            return View::render('errors/400', [], 400);
        }

        $old = null;
        if ($recordId !== null) {
            $existingStatement = $this->pdo->prepare(
                'SELECT * FROM ' . $definition['table'] . '
                 WHERE id = :id AND newsic_instance_id = :instance_id'
            );
            $existingStatement->execute(['id' => $recordId, 'instance_id' => $instanceId]);
            $old = $existingStatement->fetch();
            if (!$old) {
                return View::render('errors/404', [], 404);
            }
        }

        [$data, $errors, $formValues] = $this->validatedChildInput($definition, $old);
        if ($errors !== []) {
            return $this->show(
                $instanceId,
                array_values($errors),
                422,
                false,
                ['section' => $type, 'record_id' => $recordId === false ? null : $recordId, 'values' => $formValues]
            );
        }

        $companyId = (int) $instance['company_id'];
        $now = date('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            if ($old === null) {
                $columns = array_keys($data);
                $columns[] = 'newsic_instance_id';
                $columns[] = 'created_at';
                $columns[] = 'updated_at';
                $parameters = array_map(static fn (string $column): string => ':' . $column, $columns);
                $insert = $this->pdo->prepare(
                    'INSERT INTO ' . $definition['table'] . ' (' . implode(', ', $columns) . ')
                     VALUES (' . implode(', ', $parameters) . ')'
                );
                $insert->execute($data + [
                    'newsic_instance_id' => $instanceId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $recordId = (int) $this->pdo->lastInsertId();
                Audit::record($this->pdo, 'criacao', $definition['table'], $recordId, 'record', $companyId);
                $this->auditChildSecrets($definition, $data, $recordId, $companyId, 'criacao');
            } else {
                $assignments = array_map(
                    static fn (string $column): string => $column . ' = :' . $column,
                    array_keys($data)
                );
                $assignments[] = 'updated_at = :updated_at';
                $update = $this->pdo->prepare(
                    'UPDATE ' . $definition['table'] . '
                     SET ' . implode(', ', $assignments) . '
                     WHERE id = :id AND newsic_instance_id = :newsic_instance_id'
                );
                $update->execute($data + [
                    'updated_at' => $now,
                    'id' => $recordId,
                    'newsic_instance_id' => $instanceId,
                ]);
                foreach ($data as $column => $value) {
                    if (($old[$column] ?? null) !== $value) {
                        $field = $this->childFieldName($definition, $column);
                        $sensitive = $this->isChildSecret($definition, $column);
                        Audit::record(
                            $this->pdo,
                            'alteracao',
                            $definition['table'],
                            (int) $recordId,
                            $field,
                            $companyId,
                            $sensitive
                        );
                    }
                }
            }
            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($type === 'extensions' && str_contains(
                $exception->getMessage(),
                'UNIQUE constraint failed: newsic_extensions.newsic_instance_id, newsic_extensions.extension_number'
            )) {
                return $this->show(
                    $instanceId,
                    ['Este número de ramal já está cadastrado nesta instância.'],
                    422,
                    false,
                    ['section' => $type, 'record_id' => $recordId, 'values' => $formValues]
                );
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        Flash::success($definition['label'] . ' atualizado(s) com sucesso.');

        return Response::redirect('/newsic/' . $instanceId);
    }

    private function renderInstanceForm(
        array $company,
        array $instance,
        array $errors,
        string $mode,
        int $statusCode = 200
    ): Response {
        $documents = $this->newsicDocuments((int) $company['id'], (int) ($instance['source_document_id'] ?? 0));

        return View::render('newsic/form', [
            'company' => $company,
            'instance' => $instance,
            'documents' => $documents,
            'errors' => $errors,
            'mode' => $mode,
            'canManage' => Auth::canManage(),
        ], $statusCode);
    }

    private function validatedInstanceInput(int $companyId, ?array $existing): array
    {
        $errors = [];
        $data = [];
        $values = [];
        $secretInputs = [];
        $textFields = [
            'server_name' => ['max' => 160, 'min' => 2, 'required' => true],
            'web_username' => ['max' => 160],
            'ssh_username' => ['max' => 160],
            'freemyip_link' => ['max' => 500],
            'software_version' => ['max' => 120],
            'documentation_path' => ['max' => 500],
            'notes' => ['max' => 2000],
        ];
        foreach ($textFields as $field => $configuration) {
            $raw = $_POST[$field] ?? '';
            if (!is_string($raw)) {
                $raw = '';
                $errors[$field] = 'Informe um valor válido.';
            }
            $value = trim($raw);
            if (($configuration['required'] ?? false) && $value === '') {
                $errors[$field] = 'Este campo é obrigatório.';
            } elseif (strlen($value) < ($configuration['min'] ?? 0)) {
                $errors[$field] = 'Informe ao menos ' . $configuration['min'] . ' caracteres.';
            } elseif (strlen($value) > $configuration['max']) {
                $errors[$field] = 'O campo excede o limite permitido.';
            }
            $data[$field] = $value === '' ? null : $value;
            $values[$field] = $value;
        }

        foreach (['internal_ip', 'external_ip'] as $field) {
            $raw = $_POST[$field] ?? '';
            $value = is_string($raw) ? trim($raw) : '';
            if (!is_string($raw)) {
                $errors[$field] = 'Informe um endereço IPv4 ou IPv6 válido.';
            }
            if ($value !== '' && (!filter_var($value, FILTER_VALIDATE_IP) || strlen($value) > 45)) {
                $errors[$field] = 'Informe um endereço IPv4 ou IPv6 válido.';
            }
            $data[$field] = $value === '' ? null : $value;
            $values[$field] = $value;
        }

        foreach (['web_port', 'ssh_port'] as $field) {
            $raw = $_POST[$field] ?? '';
            $value = is_string($raw) ? trim($raw) : '';
            if (!is_string($raw)) {
                $errors[$field] = 'Informe uma porta entre 1 e 65.535.';
            }
            if ($value !== '') {
                $port = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
                if ($port === false) {
                    $errors[$field] = 'Informe uma porta entre 1 e 65.535.';
                }
            }
            $data[$field] = $value === '' ? null : $value;
            $values[$field] = $value;
        }

        $serverTypeInput = $_POST['server_type'] ?? '';
        $serverType = is_string($serverTypeInput) ? $serverTypeInput : '';
        if (!is_string($serverTypeInput)) {
            $errors['server_type'] = 'Selecione um tipo de servidor válido.';
        }
        if ($serverType !== '' && !in_array($serverType, self::SERVER_TYPES, true)) {
            $errors['server_type'] = 'Selecione um tipo de servidor válido.';
        }
        $data['server_type'] = $serverType === '' ? null : $serverType;
        $values['server_type'] = $serverType;

        $statusInput = $_POST['status'] ?? 'ativo';
        $status = is_string($statusInput) ? $statusInput : '';
        if (!in_array($status, self::INSTANCE_STATUSES, true)) {
            $errors['status'] = 'Selecione um status válido.';
            $status = 'ativo';
        }
        $data['status'] = $status;
        $values['status'] = $status;

        $sourceDocumentInput = $_POST['source_document_id'] ?? '';
        if (!is_string($sourceDocumentInput)) {
            $sourceDocumentInput = '';
            $errors['source_document_id'] = 'Selecione um documento Newsic válido.';
        }
        $sourceDocumentId = $sourceDocumentInput === '' ? null : filter_var($sourceDocumentInput, FILTER_VALIDATE_INT);
        if ($sourceDocumentInput !== '' && ($sourceDocumentId === false || $sourceDocumentId < 1)) {
            $errors['source_document_id'] = 'Selecione um documento Newsic válido.';
        } elseif ($sourceDocumentId !== null) {
            $documentStatement = $this->pdo->prepare(
                'SELECT company_id FROM documents WHERE id = :id AND document_type = \'newsic\''
            );
            $documentStatement->execute(['id' => $sourceDocumentId]);
            $documentCompanyId = $documentStatement->fetchColumn();
            if (
                $documentCompanyId === false
                || ($documentCompanyId !== null && (int) $documentCompanyId !== $companyId)
            ) {
                $errors['source_document_id'] = 'O documento não pertence a esta empresa ou não é do tipo Newsic.';
            }
        }
        $data['source_document_id'] = $sourceDocumentId === false ? null : $sourceDocumentId;
        $values['source_document_id'] = $sourceDocumentInput;

        foreach (self::SECRET_COLUMNS as $column => $field) {
            $raw = $_POST[$field] ?? '';
            unset($_POST[$field]);
            if (!is_string($raw)) {
                $errors[$field] = 'Informe um valor válido.';
                $raw = '';
            }
            $clear = ($_POST['clear_' . $field] ?? '') === '1';
            $values['has_' . $field] = !empty($existing[$column]);
            $values['clear_' . $field] = $clear ? '1' : '';
            if ($raw !== '') {
                $secretInputs[$column] = $raw;
            }
            if ($raw !== '' && $clear) {
                $errors[$field] = 'Escolha entre substituir ou remover a credencial.';
            }
            if ($clear) {
                $data[$column] = null;
            } elseif ($raw === '') {
                $data[$column] = $existing[$column] ?? null;
            } else {
                $data[$column] = $existing[$column] ?? null;
            }
        }

        if ($secretInputs !== []) {
            if ($errors === []) {
                try {
                    CredentialCipher::assertAvailable();
                    foreach ($secretInputs as $column => $raw) {
                        $data[$column] = CredentialCipher::encrypt($raw);
                    }
                } catch (RuntimeException) {
                    foreach (array_keys($secretInputs) as $column) {
                        $errors[self::SECRET_COLUMNS[$column]] =
                            'A criptografia não está configurada. Solicite a configuração segura de CREDENTIALS_KEY.';
                    }
                }
            }
            foreach ($secretInputs as &$secret) {
                if (function_exists('sodium_memzero')) {
                    sodium_memzero($secret);
                }
            }
            unset($secret);
        }

        return [$data, $values, $errors];
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

            if ($type === 'checkbox') {
                $raw = $_POST[$name] ?? null;
                if ($raw !== null && (!is_string($raw) || $raw !== '1')) {
                    $errors[$name] = 'Valor inválido.';
                }
                $data[$column] = $raw === '1' ? 1 : 0;
                $values[$name] = $data[$column];
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
                if ($value === '') {
                    $data[$column] = $field['default'] ?? null;
                } elseif (!array_key_exists($value, $options)) {
                    $errors[$name] = 'Selecione uma opção válida.';
                    $data[$column] = null;
                } else {
                    $data[$column] = $value;
                }
                $values[$name] = $value;
            } elseif ($type === 'number') {
                if ($value === '') {
                    $data[$column] = null;
                } else {
                    $number = filter_var($value, FILTER_VALIDATE_INT);
                    if ($number === false || $number < ($field['min'] ?? 0)) {
                        $errors[$name] = 'Informe um número inteiro válido.';
                        $data[$column] = null;
                    } else {
                        $data[$column] = $number;
                    }
                }
                $values[$name] = $value;
            } elseif ($type === 'ip') {
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_IP)) {
                    $errors[$name] = 'Informe um endereço IPv4 ou IPv6 válido.';
                } elseif (strlen($value) > ($field['max'] ?? 45)) {
                    $errors[$name] = 'O campo excede o limite permitido.';
                }
                $data[$column] = $value === '' ? null : $value;
                $values[$name] = $value;
            } else {
                if (($field['required'] ?? false) && $value === '') {
                    $errors[$name] = 'Este campo é obrigatório.';
                } elseif (strlen($value) > ($field['max'] ?? 500)) {
                    $errors[$name] = 'O campo excede o limite permitido.';
                }
                $data[$column] = $value === '' ? null : $value;
                $values[$name] = $value;
            }
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
                foreach ($secretInputs as $name => $raw) {
                    $column = $definition['fields'][$name]['column'];
                    $data[$column] = CredentialCipher::encrypt($raw);
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
            if (!$includeRecords) {
                $count = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM ' . $definition['table'] . '
                     WHERE newsic_instance_id = :instance_id'
                );
                $count->execute(['instance_id' => $instanceId]);
                $sections[] = [
                    'type' => $type,
                    'label' => $definition['label'],
                    'summary_field' => $definition['summary_field'],
                    'fields' => [],
                    'records' => [],
                    'record_count' => (int) $count->fetchColumn(),
                ];
                continue;
            }
            $statement = $this->pdo->prepare(
                'SELECT * FROM ' . $definition['table'] . '
                 WHERE newsic_instance_id = :instance_id ORDER BY id DESC'
            );
            $statement->execute(['instance_id' => $instanceId]);
            $records = [];
            foreach ($statement->fetchAll() as $row) {
                $secretFields = [];
                foreach ($definition['fields'] as $fieldName => $field) {
                    $column = $field['column'] ?? $fieldName;
                    if ($field['type'] === 'secret') {
                        $secretFields[$fieldName] = !empty($row[$column]);
                        unset($row[$column]);
                    }
                }
                $records[] = ['values' => $row, 'secret_fields' => $secretFields];
            }
            $sections[] = [
                'type' => $type,
                'label' => $definition['label'],
                'summary_field' => $definition['summary_field'],
                'fields' => array_map(
                    static fn (string $name, array $field): array => ['name' => $name] + $field,
                    array_keys($definition['fields']),
                    array_values($definition['fields'])
                ),
                'records' => $records,
                'record_count' => count($records),
            ];
        }

        return $sections;
    }

    private function auditChildSecrets(
        array $definition,
        array $data,
        int $recordId,
        int $companyId,
        string $action
    ): void {
        foreach ($data as $column => $value) {
            if ($value !== null && $this->isChildSecret($definition, $column)) {
                Audit::record(
                    $this->pdo,
                    $action,
                    $definition['table'],
                    $recordId,
                    $this->childFieldName($definition, $column),
                    $companyId,
                    true
                );
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

    private function findCompany(int $companyId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name FROM companies WHERE id = :id');
        $statement->execute(['id' => $companyId]);
        $company = $statement->fetch();

        return $company ?: null;
    }

    private function findInstance(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM newsic_instances WHERE id = :id');
        $statement->execute(['id' => $id]);
        $instance = $statement->fetch();

        return $instance ?: null;
    }

    private function newsicDocuments(int $companyId, int $selectedId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, original_filename, company_id FROM documents
             WHERE document_type = \'newsic\'
               AND (company_id = :company_id OR company_id IS NULL OR id = :selected_id)
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['company_id' => $companyId, 'selected_id' => $selectedId]);

        return $statement->fetchAll();
    }

    private function isActiveInstanceConflict(PDOException $exception): bool
    {
        return str_contains(
            $exception->getMessage(),
            'UNIQUE constraint failed: newsic_instances.company_id'
        );
    }
}
