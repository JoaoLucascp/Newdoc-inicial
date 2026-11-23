<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use InvalidArgumentException;
use NewDoc\Auth;
use NewDoc\Csrf;
use NewDoc\Flash;
use NewDoc\NewsicExtensionCsv;
use NewDoc\NewsicExtensionImport;
use NewDoc\Response;
use NewDoc\View;
use PDO;
use Throwable;

final class NewsicImportController
{
    public function __construct(private readonly PDO $pdo) {}

    public function handle(int $id): Response
    {
        return $this->privateResponse($this->dispatch($id));
    }

    private function dispatch(int $id): Response
    {
        if (!Auth::canManage() || (int) (Auth::user()['id'] ?? 0) < 1) return View::render('errors/403', [], 403);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'POST'], true)) return new Response(405, '', ['Allow' => 'GET, POST']);
        if ($method === 'POST' && !Csrf::verify($_POST['_csrf'] ?? null)) return View::render('errors/419', [], 419);
        $query = $this->pdo->prepare('SELECT n.id, n.server_name, n.company_id, c.name AS company_name FROM newsic_instances n JOIN companies c ON c.id = n.company_id WHERE n.id = :id');
        $query->execute(['id' => $id]);
        $instance = $query->fetch(PDO::FETCH_ASSOC);
        if (!$instance) return View::render('errors/404', [], 404);
        $stored = $_SESSION['newsic_csv_draft'] ?? null;
        if (is_array($stored) && (int) ($stored['expires'] ?? 0) <= time()) unset($_SESSION['newsic_csv_draft']);
        if ($method === 'GET' && ($_GET['modelo'] ?? '') === 'csv') {
            $stream = fopen('php://memory', 'w+');
            if ($stream === false) return new Response(500, 'Não foi possível gerar o modelo.');
            try {
                fputcsv($stream, NewsicExtensionCsv::headers(), ';', '"', '');
                rewind($stream);
                $csv = stream_get_contents($stream);
            } finally { fclose($stream); }
            if ($csv === false) return new Response(500, 'Não foi possível gerar o modelo.');
            return new Response(200, "\xEF\xBB\xBF" . $csv, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="modelo-ramais-newsic.csv"']);
        }
        $error = null;
        $rows = [];
        $token = '';
        $expires = 0;
        $status = 200;
        if ($method === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'cancel') {
                $draft = $_SESSION['newsic_csv_draft'] ?? null;
                if (is_array($draft) && ($draft['instance_id'] ?? null) === $id) unset($_SESSION['newsic_csv_draft']);
                return Response::redirect('/newsic/' . $id . '#ramais');
            }
            if ($action === 'preview') {
                unset($_SESSION['newsic_csv_draft']);
                try {
                    $file = $_FILES['batch'] ?? null;
                    if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK
                        || !is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null)
                        || !is_uploaded_file($file['tmp_name'])
                        || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv'
                        || (int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 2097152
                    ) throw new InvalidArgumentException('Envie um arquivo CSV de até 2 MiB. XLSX ainda não está habilitado.');
                    $csv = file_get_contents($file['tmp_name'], false, null, 0, 2097153);
                    if ($csv === false) throw new InvalidArgumentException('Não foi possível ler o arquivo enviado.');
                    $preview = (new NewsicExtensionImport($this->pdo))->preview($id, $csv);
                    unset($csv);
                    $token = bin2hex(random_bytes(32));
                    $expires = $preview['expires'];
                    $rows = $preview['rows'];
                    $_SESSION['newsic_csv_draft'] = [
                        'instance_id' => $id, 'user_id' => (int) Auth::user()['id'],
                        'token' => $token, 'expires' => $expires, 'sealed' => $preview['sealed'],
                    ];
                    unset($preview);
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                    $status = 422;
                } catch (Throwable) {
                    $error = 'Não foi possível preparar o lote. Verifique a criptografia e se há ramais já cadastrados neste servidor. Nenhum registro foi gravado.';
                    $status = 422;
                }
            } elseif ($action === 'confirm') {
                $draft = $_SESSION['newsic_csv_draft'] ?? null;
                $providedToken = $_POST['draft_token'] ?? null;
                if (!is_array($draft) || !is_string($providedToken)
                    || !is_string($draft['token'] ?? null) || !hash_equals($draft['token'], $providedToken)
                    || ($draft['instance_id'] ?? null) !== $id || ($draft['user_id'] ?? null) !== (int) Auth::user()['id']
                    || (int) ($draft['expires'] ?? 0) <= time() || ($_POST['confirm_import'] ?? '') !== '1'
                    || !is_string($draft['sealed'] ?? null)
                ) {
                    $error = 'Confirmação inválida ou expirada. Envie o arquivo novamente e gere uma nova prévia.';
                    $status = 422;
                } else {
                    unset($_SESSION['newsic_csv_draft']);
                    try {
                        $count = (new NewsicExtensionImport($this->pdo))->commit($id, $draft['sealed']);
                        Flash::success($count . ' ramal(is) importado(s) com sucesso. Nenhum registro existente foi sobrescrito.');
                        return Response::redirect('/newsic/' . $id . '#ramais');
                    } catch (Throwable) {
                        $error = 'O lote não foi importado. Nenhum ramal deste lote foi gravado. Gere uma nova prévia e tente novamente.';
                        $status = 422;
                    }
                }
            } else {
                return View::render('errors/400', [], 400);
            }
        }
        return View::render('newsic/import', compact('instance', 'error', 'rows', 'token', 'expires'), $status);
    }

    private function privateResponse(Response $response): Response
    {
        return new Response($response->status, $response->body, array_merge($response->headers, [
            'Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
        ]));
    }
}
