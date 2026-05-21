<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Database;
use App\Helpers\Request;
use App\Helpers\Response;

class AuthMiddleware
{
    public static function handle(Request $request): void
    {
        $token = $request->bearerToken();

        if (!$token) {
            Response::unauthorized('Token de autenticação não fornecido.');
        }

        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT t.user_id
            FROM tokens t
            WHERE t.token = :token
              AND t.expires_at > datetime('now')
        ");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::unauthorized('Token inválido ou expirado. Faça login novamente.');
        }

        $request->userId = (int) $row['user_id'];
    }
}
