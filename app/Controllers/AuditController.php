<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use NewDoc\Auth;
use NewDoc\Response;
use NewDoc\View;
use PDO;

final class AuditController
{
    private const PAGE_SIZE = 25;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(): Response
    {
        if (!Auth::canManage()) {
            return View::render('errors/403', [], 403);
        }

        $action = (string) ($_GET['action'] ?? '');
        $validActions = ['criacao', 'alteracao', 'exclusao', 'consulta', 'importacao', 'login', 'logout'];
        if (!in_array($action, $validActions, true)) {
            $action = '';
        }
        $table = trim(substr((string) ($_GET['table'] ?? ''), 0, 80));
        if (!preg_match('/^[a-z_]*$/', $table)) {
            $table = '';
        }

        $where = [];
        $parameters = [];
        if ($action !== '') {
            $where[] = 'a.action = :action';
            $parameters['action'] = $action;
        }
        if ($table !== '') {
            $where[] = 'a.table_name = :table_name';
            $parameters['table_name'] = $table;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs a' . $whereSql);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;
        $statement = $this->pdo->prepare(
            'SELECT a.id, a.action, a.table_name, a.record_id, a.field_name,
                    a.sensitive_field, a.ip_address, a.created_at, u.name AS user_name,
                    c.id AS company_id, c.name AS company_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN companies c ON c.id = a.company_id'
            . $whereSql . '
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset
        );
        $statement->execute($parameters);

        return View::render('audit/index', [
            'events' => $statement->fetchAll(),
            'action' => $action,
            'table' => $table,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'actions' => $validActions,
        ]);
    }
}
