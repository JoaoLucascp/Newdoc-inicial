<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use NewDoc\Auth;
use NewDoc\Csrf;
use NewDoc\Flash;
use NewDoc\NewsicExtensionDelete;
use NewDoc\Response;
use NewDoc\View;
use PDO;
use Throwable;

final class NewsicDeleteController
{
    public function __construct(private readonly PDO $pdo) {}

    public function handle(int $id): Response
    {
        $response = $this->dispatch($id);
        return new Response($response->status, $response->body, array_merge($response->headers, [
            'Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
        ]));
    }

    private function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    private function json(array $data, int $status = 200): Response
    {
        return new Response($status, json_encode($data, JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    private function dispatch(int $id): Response
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        if (!Auth::canManage() || $userId < 1) return $this->wantsJson() ? $this->json(['success' => false, 'error' => 'Acesso negado.'], 403) : View::render('errors/403', [], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return new Response(405, '', ['Allow' => 'POST']);
        if (!Csrf::verify($_POST['_csrf'] ?? null)) return $this->wantsJson() ? $this->json(['success' => false, 'error' => 'Sessão ou formulário inválido.'], 419) : View::render('errors/419', [], 419);
        $query = $this->pdo->prepare('SELECT n.id, n.company_id, n.server_name, c.name AS company_name FROM newsic_instances n JOIN companies c ON c.id = n.company_id WHERE n.id = :id');
        $query->execute(['id' => $id]);
        $instance = $query->fetch(PDO::FETCH_ASSOC);
        $query->closeCursor();
        if (!$instance) return $this->wantsJson() ? $this->json(['success' => false, 'error' => 'Servidor não encontrado.'], 404) : View::render('errors/404', [], 404);
        $error = null;
        $rows = [];
        $token = '';
        $expires = 0;
        $status = 200;
        $action = $_POST['action'] ?? '';
        if ($action === 'cancel') {
            $draft = $_SESSION['newsic_delete_draft'] ?? null;
            $providedToken = $_POST['draft_token'] ?? null;
            $matchingToken = is_array($draft) && is_string($providedToken) && is_string($draft['token'] ?? null) && hash_equals($draft['token'], $providedToken);
            if (is_array($draft) && ($draft['instance_id'] ?? null) === $id && ($draft['user_id'] ?? null) === $userId
                && (!$this->wantsJson() || $matchingToken)
            ) unset($_SESSION['newsic_delete_draft']);
            return $this->wantsJson() ? $this->json(['success' => true]) : Response::redirect('/newsic/' . $id . '#ramais');
        }
        if ($action === 'preview') {
            unset($_SESSION['newsic_delete_draft']);
            $ids = $_POST['ids'] ?? [];
            try {
                if (!is_array($ids)) throw new \RuntimeException('Seleção inválida.');
                $preview = (new NewsicExtensionDelete($this->pdo))->preview($id, $ids);
                $instance = $preview['instance'];
                $rows = $preview['rows'];
                $expires = $preview['expires'];
                $token = bin2hex(random_bytes(32));
                $_SESSION['newsic_delete_draft'] = [
                    'instance_id' => $id, 'user_id' => $userId, 'token' => $token,
                    'expires' => $expires, 'sealed' => $preview['sealed'],
                ];
                unset($preview);
            } catch (Throwable) {
                $error = 'Não foi possível preparar a exclusão. Selecione de 1 a 500 ramais deste servidor e tente novamente. Nenhum ramal foi excluído.';
                $status = 422;
            }
        } elseif ($action === 'confirm') {
            $draft = $_SESSION['newsic_delete_draft'] ?? null;
            $providedToken = $_POST['draft_token'] ?? null;
            if (!is_array($draft) || !is_string($providedToken) || !is_string($draft['token'] ?? null)
                || !hash_equals($draft['token'], $providedToken) || ($draft['instance_id'] ?? null) !== $id
                || ($draft['user_id'] ?? null) !== $userId || (int) ($draft['expires'] ?? 0) <= time()
                || ($_POST['confirm_delete'] ?? '') !== '1' || !is_string($draft['sealed'] ?? null)
            ) {
                $error = 'Confirmação inválida ou expirada. Volte à tabela e selecione os ramais novamente. Nenhum ramal foi excluído.';
                $status = 422;
            } else {
                unset($_SESSION['newsic_delete_draft']);
                try {
                    $count = (new NewsicExtensionDelete($this->pdo))->commit($id, $draft['sealed'], true);
                    Flash::success($count . ' ramal(is) excluído(s) definitivamente. Operação registrada na auditoria.');
                    return $this->wantsJson() ? $this->json(['success' => true, 'count' => $count]) : Response::redirect('/newsic/' . $id . '#ramais');
                } catch (Throwable) {
                    $error = 'Nenhum ramal foi excluído. A seleção pode ter mudado; volte à tabela e gere uma nova confirmação.';
                    $status = 422;
                }
            }
        } else {
            return $this->wantsJson() ? $this->json(['success' => false, 'error' => 'Solicitação inválida.'], 400) : View::render('errors/400', [], 400);
        }
        if ($this->wantsJson()) {
            return $error !== null
                ? $this->json(['success' => false, 'error' => $error], $status)
                : $this->json(['success' => true, 'instance' => $instance, 'rows' => $rows, 'token' => $token, 'expires' => $expires]);
        }
        return View::render('newsic/delete', compact('instance', 'error', 'rows', 'token', 'expires'), $status);
    }
}
