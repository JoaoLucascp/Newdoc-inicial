<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use NewDoc\Audit;
use NewDoc\Auth;
use NewDoc\Database;
use NewDoc\Flash;
use NewDoc\Response;
use NewDoc\Validator;
use NewDoc\View;
use PDO;

final class CompanyController
{
    private const PAGE_SIZE = 15;
    private const STATUSES = ['ativo', 'inativo', 'inexistente'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(): Response
    {
        $query = trim(substr((string) ($_GET['q'] ?? ''), 0, 160));
        $status = (string) ($_GET['status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $where = [];
        $parameters = [];
        if ($query !== '') {
            $where[] = '(name LIKE :q1 ESCAPE \'\\\' OR trade_name LIKE :q2 ESCAPE \'\\\'
                OR cnpj LIKE :q3 ESCAPE \'\\\' OR email LIKE :q4 ESCAPE \'\\\'
                OR responsible_name LIKE :q5 ESCAPE \'\\\' OR responsible_phone LIKE :q6 ESCAPE \'\\\')';
            $needle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
            foreach (range(1, 6) as $index) {
                $parameters['q' . $index] = $needle;
            }
        }
        if ($status !== '') {
            $where[] = 'contract_status = :status';
            $parameters['status'] = $status;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM companies' . $whereSql);
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;
        $statement = $this->pdo->prepare(
            'SELECT id, name, trade_name, cnpj, email, responsible_name,
                    contract_status, updated_at
             FROM companies' . $whereSql . '
             ORDER BY name COLLATE NOCASE, id DESC
             LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset
        );
        $statement->execute($parameters);

        return View::render('companies/index', [
            'companies' => $statement->fetchAll(),
            'query' => $query,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'canManage' => Auth::canManage(),
        ]);
    }

    public function create(): Response
    {
        return View::render('companies/form', [
            'company' => [],
            'errors' => [],
            'mode' => 'create',
            'duplicateWarning' => false,
        ]);
    }

    public function store(): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }

        [$data, $errors] = $this->validatedInput();
        $duplicateWarning = false;
        if ($data['name'] !== null) {
            $duplicateWarning = $this->hasDuplicateName((string) $data['name'], null);
            if ($duplicateWarning && ($_POST['confirm_duplicate'] ?? '') !== '1') {
                $errors['confirm_duplicate'] = 'Já existe empresa com este nome. Confirme se deseja cadastrar outra.';
            }
        }

        if ($errors !== []) {
            return View::render('companies/form', [
                'company' => $data,
                'errors' => $errors,
                'mode' => 'create',
                'duplicateWarning' => $duplicateWarning,
            ], 422);
        }

        $now = date('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO companies
                (name, trade_name, cnpj, responsible_name, responsible_phone, email, address,
                 contract_start_date, contract_end_date, contract_status, notes,
                 created_at, updated_at, created_by, updated_by)
             VALUES
                (:name, :trade_name, :cnpj, :responsible_name, :responsible_phone, :email, :address,
                 :contract_start_date, :contract_end_date, :contract_status, :notes,
                 :created_at, :updated_at, :created_by, :updated_by)'
        );
        $this->pdo->beginTransaction();
        try {
            $statement->execute($data + [
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => Auth::user()['id'],
                'updated_by' => Auth::user()['id'],
            ]);
            $id = (int) $this->pdo->lastInsertId();
            Audit::record($this->pdo, 'criacao', 'companies', $id, 'record', $id);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
        Flash::success('Empresa cadastrada com sucesso.');

        return Response::redirect('/empresas/' . $id);
    }

    public function show(int $id): Response
    {
        $statement = $this->pdo->prepare('SELECT * FROM companies WHERE id = :id');
        $statement->execute(['id' => $id]);
        $company = $statement->fetch();
        if (!$company) {
            return View::render('errors/404', [], 404);
        }

        $documentsStatement = $this->pdo->prepare(
            'SELECT id, original_filename, document_type, source_status, is_current, created_at
             FROM documents WHERE company_id = :company_id
             ORDER BY created_at DESC, id DESC LIMIT 8'
        );
        $documentsStatement->execute(['company_id' => $id]);
        $newsicStatement = $this->pdo->prepare(
            'SELECT id, server_name, server_type, status, software_version, updated_at
             FROM newsic_instances WHERE company_id = :company_id
             ORDER BY CASE status WHEN \'ativo\' THEN 0 ELSE 1 END, updated_at DESC, id DESC'
        );
        $newsicStatement->execute(['company_id' => $id]);
        $auditStatement = $this->pdo->prepare(
            'SELECT action, table_name, record_id, field_name, created_at
             FROM audit_logs WHERE company_id = :company_id
             ORDER BY created_at DESC, id DESC LIMIT 8'
        );
        $auditStatement->execute(['company_id' => $id]);
        Audit::record($this->pdo, 'consulta', 'companies', $id, null, $id);

        return View::render('companies/show', [
            'company' => $company,
            'documents' => $documentsStatement->fetchAll(),
            'newsicInstances' => $newsicStatement->fetchAll(),
            'auditEvents' => $auditStatement->fetchAll(),
            'canManage' => Auth::canManage(),
        ]);
    }

    public function edit(int $id): Response
    {
        $statement = $this->pdo->prepare('SELECT * FROM companies WHERE id = :id');
        $statement->execute(['id' => $id]);
        $company = $statement->fetch();
        if (!$company) {
            return View::render('errors/404', [], 404);
        }

        return View::render('companies/form', [
            'company' => $company,
            'errors' => [],
            'mode' => 'edit',
            'duplicateWarning' => false,
        ]);
    }

    public function update(int $id): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }
        $existingStatement = $this->pdo->prepare('SELECT id FROM companies WHERE id = :id');
        $existingStatement->execute(['id' => $id]);
        if (!$existingStatement->fetchColumn()) {
            return View::render('errors/404', [], 404);
        }

        [$data, $errors] = $this->validatedInput();
        $duplicateWarning = false;
        if ($data['name'] !== null) {
            $duplicateWarning = $this->hasDuplicateName((string) $data['name'], $id);
            if ($duplicateWarning && ($_POST['confirm_duplicate'] ?? '') !== '1') {
                $errors['confirm_duplicate'] = 'Já existe outra empresa com este nome. Confirme se deseja mantê-lo.';
            }
        }

        if ($errors !== []) {
            return View::render('companies/form', [
                'company' => $data + ['id' => $id],
                'errors' => $errors,
                'mode' => 'edit',
                'duplicateWarning' => $duplicateWarning,
            ], 422);
        }

        $now = date('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE companies SET
                name = :name, trade_name = :trade_name, cnpj = :cnpj,
                responsible_name = :responsible_name, responsible_phone = :responsible_phone,
                email = :email, address = :address, contract_start_date = :contract_start_date,
                contract_end_date = :contract_end_date, contract_status = :contract_status,
                notes = :notes, updated_at = :updated_at, updated_by = :updated_by
             WHERE id = :id'
        );
        $this->pdo->beginTransaction();
        try {
            $oldStatement = $this->pdo->prepare('SELECT * FROM companies WHERE id = :id');
            $oldStatement->execute(['id' => $id]);
            $old = $oldStatement->fetch();
            if (!$old) {
                $this->pdo->rollBack();
                return View::render('errors/404', [], 404);
            }
            $statement->execute($data + [
                'updated_at' => $now,
                'updated_by' => Auth::user()['id'],
                'id' => $id,
            ]);
            foreach (array_keys($data) as $field) {
                if (($old[$field] ?? null) !== $data[$field]) {
                    Audit::record($this->pdo, 'alteracao', 'companies', $id, $field, $id);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
        Flash::success('Dados da empresa atualizados com sucesso.');

        return Response::redirect('/empresas/' . $id);
    }

    private function validatedInput(): array
    {
        $errors = [];
        $rawName = trim((string) ($_POST['name'] ?? ''));
        if ($rawName === '' || strlen($rawName) > 200) {
            $errors['name'] = 'Informe um nome com até 200 caracteres.';
        }

        $emailInput = trim((string) ($_POST['email'] ?? ''));
        $email = $emailInput === '' ? null : Validator::email($emailInput);
        if ($emailInput !== '' && $email === null) {
            $errors['email'] = 'Informe um e-mail válido com até 254 caracteres.';
        }

        $textFields = [
            'trade_name' => 200,
            'cnpj' => 30,
            'responsible_name' => 160,
            'responsible_phone' => 40,
            'address' => 500,
            'notes' => 2000,
        ];
        $data = ['name' => $rawName === '' ? null : $rawName];
        foreach ($textFields as $field => $limit) {
            $input = trim((string) ($_POST[$field] ?? ''));
            $value = Validator::text($input, $limit);
            if ($input !== '' && $value === null) {
                $errors[$field] = 'O campo excede o limite permitido.';
            }
            $data[$field] = $value;
        }
        $data['email'] = $email;

        foreach (['contract_start_date', 'contract_end_date'] as $field) {
            $input = trim((string) ($_POST[$field] ?? ''));
            $date = Validator::date($input);
            if ($input !== '' && $date === null) {
                $errors[$field] = 'Informe uma data válida.';
            }
            $data[$field] = $date;
        }
        if (
            $data['contract_start_date'] !== null
            && $data['contract_end_date'] !== null
            && $data['contract_end_date'] < $data['contract_start_date']
        ) {
            $errors['contract_end_date'] = 'O fim do contrato não pode anteceder o início.';
        }

        $status = (string) ($_POST['contract_status'] ?? 'inexistente');
        if (!in_array($status, self::STATUSES, true)) {
            $errors['contract_status'] = 'Selecione uma situação de contrato válida.';
            $status = 'inexistente';
        }
        $data['contract_status'] = $status;

        return [$data, $errors];
    }

    private function hasDuplicateName(string $name, ?int $exceptId): bool
    {
        $sql = 'SELECT 1 FROM companies WHERE name = :name COLLATE NOCASE';
        $parameters = ['name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $parameters['id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (bool) $statement->fetchColumn();
    }
}
