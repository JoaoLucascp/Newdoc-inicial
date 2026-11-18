<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use NewDoc\Auth;
use NewDoc\View;
use PDO;

final class DashboardController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(): \NewDoc\Response
    {
        $metrics = [
            'companies' => (int) $this->pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn(),
            'active_companies' => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM companies WHERE contract_status = 'ativo'"
            )->fetchColumn(),
            'pending_documents' => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM documents WHERE source_status = 'pendente'"
            )->fetchColumn(),
            'documents' => (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn(),
            'active_newsic_instances' => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM newsic_instances WHERE status = 'ativo'"
            )->fetchColumn(),
        ];
        $recent = $this->pdo->query(
            'SELECT id, name, trade_name, contract_status, updated_at
             FROM companies ORDER BY updated_at DESC, id DESC LIMIT 6'
        )->fetchAll();

        return View::render('dashboard', [
            'metrics' => $metrics,
            'recentCompanies' => $recent,
            'canManage' => Auth::canManage(),
        ]);
    }
}
