<?php

declare(strict_types=1);

namespace NewDoc;

use NewDoc\Controllers\AuditController;
use NewDoc\Controllers\AuthController;
use NewDoc\Controllers\CompanyController;
use NewDoc\Controllers\CredentialController;
use NewDoc\Controllers\DashboardController;
use NewDoc\Controllers\DocumentController;
use NewDoc\Controllers\NewbotController;
use NewDoc\Controllers\NewsicController;
use PDO;

final class Application
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function handle(): Response
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        if ($method === 'POST' && !Csrf::verify($_POST['_csrf'] ?? null)) {
            return View::render('errors/419', [], 419);
        }

        if ($path === '/login') {
            $controller = new AuthController($this->pdo);
            if ($method === 'GET') {
                return $controller->loginForm();
            }
            if ($method === 'POST') {
                return $controller->login();
            }

            return View::render('errors/404', [], 404);
        }

        if (Auth::user() === null) {
            return Response::redirect('/login');
        }

        if ($path === '/logout' && $method === 'POST') {
            return (new AuthController($this->pdo))->logout();
        }
        if ($path === '/' && $method === 'GET') {
            return (new DashboardController($this->pdo))->index();
        }
        if ($path === '/empresas' && $method === 'GET') {
            return (new CompanyController($this->pdo))->index();
        }
        if ($path === '/empresas/nova' && $method === 'GET') {
            return Auth::canManage()
                ? (new CompanyController($this->pdo))->create()
                : View::render('errors/403', [], 403);
        }
        if ($path === '/empresas' && $method === 'POST') {
            return (new CompanyController($this->pdo))->store();
        }
        if (preg_match('#^/empresas/([1-9][0-9]*)/editar$#', $path, $matches) === 1 && $method === 'GET') {
            return Auth::canManage()
                ? (new CompanyController($this->pdo))->edit((int) $matches[1])
                : View::render('errors/403', [], 403);
        }
        if (preg_match('#^/empresas/([1-9][0-9]*)$#', $path, $matches) === 1) {
            $controller = new CompanyController($this->pdo);
            if ($method === 'GET') {
                return $controller->show((int) $matches[1]);
            }
            if ($method === 'POST') {
                return $controller->update((int) $matches[1]);
            }
        }
        if ($path === '/newsic' && $method === 'GET') {
            return (new NewsicController($this->pdo))->index();
        }
        if (preg_match('#^/empresas/([1-9][0-9]*)/newsic$#', $path, $matches) === 1) {
            $controller = new NewsicController($this->pdo);
            if ($method === 'GET') {
                return $controller->company((int) $matches[1]);
            }
            if ($method === 'POST') {
                return $controller->store((int) $matches[1]);
            }
        }
        if (preg_match('#^/empresas/([1-9][0-9]*)/newsic/nova$#', $path, $matches) === 1 && $method === 'GET') {
            return (new NewsicController($this->pdo))->create((int) $matches[1]);
        }
        if (preg_match('#^/newsic/([1-9][0-9]*)/exportar$#', $path, $matches) === 1) {
            return (new \NewDoc\Controllers\NewsicExportController($this->pdo))->handle((int) $matches[1]);
        }
        if (preg_match('#^/newsic/([1-9][0-9]*)/ramais/lote$#', $path, $matches) === 1) {
            return (new \NewDoc\Controllers\NewsicImportController($this->pdo))->handle((int) $matches[1]);
        }
        if (preg_match('#^/newsic/([1-9][0-9]*)/ramais/excluir$#', $path, $matches) === 1) {
            return (new \NewDoc\Controllers\NewsicDeleteController($this->pdo))->handle((int) $matches[1]);
        }
        if (preg_match('#^/newsic/([1-9][0-9]*)/editar$#', $path, $matches) === 1 && $method === 'GET') {
            return (new NewsicController($this->pdo))->edit((int) $matches[1]);
        }
        if (preg_match('#^/newsic/([1-9][0-9]*)/dados$#', $path, $matches) === 1 && $method === 'POST') {
            return (new NewsicController($this->pdo))->saveChild((int) $matches[1]);
        }
        if (preg_match('#^/newsic/([1-9][0-9]*)$#', $path, $matches) === 1) {
            $controller = new NewsicController($this->pdo);
            if ($method === 'GET') {
                return $controller->show((int) $matches[1]);
            }
            if ($method === 'POST') {
                return $controller->update((int) $matches[1]);
            }
        }
        if ($path === '/newbot' && $method === 'GET') {
            return (new NewbotController($this->pdo))->index();
        }
        if (preg_match('#^/empresas/([1-9][0-9]*)/newbot$#', $path, $matches) === 1) {
            $controller = new NewbotController($this->pdo);
            if ($method === 'GET') {
                return $controller->company((int) $matches[1]);
            }
            if ($method === 'POST') {
                return $controller->store((int) $matches[1]);
            }
        }
        if (preg_match('#^/empresas/([1-9][0-9]*)/newbot/nova$#', $path, $matches) === 1 && $method === 'GET') {
            return (new NewbotController($this->pdo))->create((int) $matches[1]);
        }
        if (preg_match('#^/newbot/([1-9][0-9]*)/editar$#', $path, $matches) === 1 && $method === 'GET') {
            return (new NewbotController($this->pdo))->edit((int) $matches[1]);
        }
        if (preg_match('#^/newbot/([1-9][0-9]*)/dados$#', $path, $matches) === 1 && $method === 'POST') {
            return (new NewbotController($this->pdo))->saveChild((int) $matches[1]);
        }
        if (preg_match('#^/newbot/([1-9][0-9]*)$#', $path, $matches) === 1) {
            $controller = new NewbotController($this->pdo);
            if ($method === 'GET') {
                return $controller->show((int) $matches[1]);
            }
            if ($method === 'POST') {
                return $controller->update((int) $matches[1]);
            }
        }
        if (preg_match('#^/credenciais/([a-z_]+)/([1-9][0-9]*)/revelar$#', $path, $matches) === 1 && $method === 'POST') {
            return (new CredentialController($this->pdo))->reveal($matches[1], (int) $matches[2]);
        }
        if ($path === '/documentos' && $method === 'GET') {
            return (new DocumentController($this->pdo))->index();
        }
        if ($path === '/documentos/novo' && $method === 'GET') {
            return Auth::canManage()
                ? (new DocumentController($this->pdo))->create()
                : View::render('errors/403', [], 403);
        }
        if ($path === '/documentos' && $method === 'POST') {
            return (new DocumentController($this->pdo))->store();
        }
        if (preg_match('#^/documentos/([1-9][0-9]*)/status$#', $path, $matches) === 1 && $method === 'POST') {
            return (new DocumentController($this->pdo))->updateStatus((int) $matches[1]);
        }
        if ($path === '/auditoria' && $method === 'GET') {
            return (new AuditController($this->pdo))->index();
        }

        return View::render('errors/404', [], 404);
    }
}
