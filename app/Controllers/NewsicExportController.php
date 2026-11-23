<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use NewDoc\Auth;
use NewDoc\Csrf;
use NewDoc\NewsicExport;
use NewDoc\Response;
use NewDoc\View;
use PDO;
use Throwable;

final class NewsicExportController
{
    public function __construct(private readonly PDO $pdo) {}

    public function handle(int $id): Response
    {
        $response = $this->dispatch($id);
        return new Response($response->status, $response->body, array_merge($response->headers, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]));
    }

    private function dispatch(int $id): Response
    {
        if (!Auth::canManage() || !is_int(Auth::user()['id'] ?? null)) return View::render('errors/403', [], 403);
        $userQuery = $this->pdo->prepare('SELECT role, active FROM users WHERE id = ?');
        $userQuery->execute([Auth::user()['id']]);
        $user = $userQuery->fetch(PDO::FETCH_ASSOC);
        if (!$user || (int) $user['active'] !== 1 || !in_array($user['role'], ['admin', 'technician'], true)) return View::render('errors/403', [], 403);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'POST'], true)) return new Response(405, '', ['Allow' => 'GET, POST']);
        if ($method === 'POST' && !Csrf::verify($_POST['_csrf'] ?? null)) return View::render('errors/419', [], 419);
        $query = $this->pdo->prepare('SELECT n.id, n.company_id, n.server_name, c.name AS company_name FROM newsic_instances n JOIN companies c ON c.id = n.company_id WHERE n.id = ?');
        $query->execute([$id]);
        $instance = $query->fetch(PDO::FETCH_ASSOC);
        if (!$instance) return View::render('errors/404', [], 404);
        $error = null;
        $format = 'pdf';
        $status = 200;
        if ($method === 'POST') {
            $format = $_POST['format'] ?? '';
            if (!is_string($format) || !in_array($format, ['csv', 'pdf'], true) || ($_POST['confirm_secrets'] ?? null) !== '1') {
                $error = 'Escolha PDF ou CSV e confirme que o arquivo conterá senhas legíveis.';
                $format = is_string($format) && in_array($format, ['csv', 'pdf'], true) ? $format : 'pdf';
                $status = 422;
            } else {
                try {
                    $report = (new NewsicExport($this->pdo))->generate($id, $format, true);
                    return new Response(200, $report['content'], [
                        'Content-Type' => $report['content_type'],
                        'Content-Disposition' => 'attachment; filename="' . $report['filename'] . '"',
                    ]);
                } catch (Throwable) {
                    $error = 'Não foi possível gerar o arquivo. Nenhum download foi disponibilizado. Verifique as dependências, a criptografia e a auditoria antes de tentar novamente.';
                    $status = 500;
                }
            }
        }
        return View::render('newsic/export', compact('instance', 'error', 'format'), $status);
    }
}
