<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Request;
use App\Helpers\Response;

class AuthController
{
    public function register(Request $request): void
    {
        $errors = $request->validate([
            'name'     => 'required|min:2|max:100',
            'email'    => 'required|email',
            'password' => 'required|min:6|max:72',
        ]);

        if ($errors) {
            Response::error('Dados inválidos.', 422, $errors);
        }

        $pdo = Database::getInstance();

        // E-mail duplicado?
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $check->execute([':email' => $request->input('email')]);
        if ($check->fetch()) {
            Response::error('Este e-mail já está em uso.', 409);
        }

        $stmt = $pdo->prepare('
            INSERT INTO users (name, email, password)
            VALUES (:name, :email, :password)
        ');
        $stmt->execute([
            ':name'     => trim($request->input('name')),
            ':email'    => strtolower(trim($request->input('email'))),
            ':password' => password_hash($request->input('password'), PASSWORD_BCRYPT, ['cost' => 12]),
        ]);

        $userId = (int) $pdo->lastInsertId();
        $user   = $pdo->prepare('SELECT id, name, email, created_at FROM users WHERE id = :id');
        $user->execute([':id' => $userId]);

        Response::success($user->fetch(), 'Conta criada com sucesso!', 201);
    }

    public function login(Request $request): void
    {
        $errors = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($errors) {
            Response::error('Dados inválidos.', 422, $errors);
        }

        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => strtolower(trim($request->input('email')))]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($request->input('password'), $user['password'])) {
            Response::error('Credenciais inválidas.', 401);
        }

        // Cria token
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . ($_ENV['TOKEN_TTL_HOURS'] ?? 24) . ' hours'));

        // Remove tokens antigos do usuário (limpeza)
        $pdo->prepare("DELETE FROM tokens WHERE user_id = :uid AND expires_at <= datetime('now')")
            ->execute([':uid' => $user['id']]);

        $pdo->prepare('INSERT INTO tokens (user_id, token, expires_at) VALUES (:uid, :token, :exp)')
            ->execute([':uid' => $user['id'], ':token' => $token, ':exp' => $expiresAt]);

        Response::success([
            'token'      => $token,
            'expires_at' => $expiresAt,
            'user'       => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
            ],
        ], 'Login realizado com sucesso!');
    }
}
