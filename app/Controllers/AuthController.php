<?php

declare(strict_types=1);

namespace NewDoc\Controllers;

use NewDoc\Audit;
use NewDoc\Auth;
use NewDoc\Csrf;
use NewDoc\LoginThrottle;
use NewDoc\PasswordPolicy;
use NewDoc\Response;
use NewDoc\Session;
use NewDoc\View;
use PDO;

final class AuthController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function loginForm(?string $message = null): Response
    {
        return View::render('auth/login', ['message' => $message]);
    }

    public function login(): Response
    {
        $email = strtolower(trim(substr((string) ($_POST['email'] ?? ''), 0, 254)));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $throttle = new LoginThrottle($this->pdo);
        $ip = Audit::clientIp();

        if ($email === '' || $throttle->isBlocked($email, $ip)) {
            return $this->loginForm(
                $email === ''
                    ? 'Não foi possível autenticar. Confira os dados e tente novamente.'
                    : 'Muitas tentativas de acesso. Aguarde 15 minutos e tente novamente.'
            );
        }

        $passwordIsValid = PasswordPolicy::isValid($password);
        $statement = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, role
             FROM users
             WHERE email = :email AND active = 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        $passwordMatches = $user !== false
            && $passwordIsValid
            && password_verify($password, (string) $user['password_hash']);
        if (!$passwordMatches) {
            $throttle->recordFailure($email, $ip);
            Audit::record($this->pdo, 'login', 'users', null, 'authentication_failed');

            return $this->loginForm(
                $passwordIsValid
                    ? 'Não foi possível autenticar. Confira os dados e tente novamente.'
                    : 'A senha deve ter entre 6 e 12 caracteres. Não use espaços no início ou no fim.'
            );
        }

        $throttle->clear($email, $ip);
        Session::regenerate();
        unset($_SESSION['_csrf']);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];
        Audit::record($this->pdo, 'login', 'users', (int) $user['id'], 'authentication_succeeded');

        return Response::redirect('/');
    }

    public function logout(): Response
    {
        $userId = Auth::user()['id'] ?? null;
        Audit::record($this->pdo, 'logout', 'users', $userId ? (int) $userId : null);
        Session::destroy();

        return Response::redirect('/login');
    }
}
