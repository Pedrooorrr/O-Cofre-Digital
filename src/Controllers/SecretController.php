<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Crypto;
use App\Helpers\Database;
use App\Helpers\Request;
use App\Helpers\Response;

class SecretController
{
    // GET /secrets
    public function index(Request $request): void
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('
            SELECT id, title, type, is_favorite, created_at, updated_at
            FROM secrets
            WHERE user_id = :uid
            ORDER BY is_favorite DESC, updated_at DESC
        ');
        $stmt->execute([':uid' => $request->userId]);
        $secrets = $stmt->fetchAll();

        // Nunca retorna o conteúdo no índice
        Response::success($secrets, 'Segredos recuperados com sucesso.');
    }

    // POST /secrets
    public function store(Request $request): void
    {
        $errors = $request->validate([
            'title'   => 'required|min:1|max:200',
            'content' => 'required|min:1',
            'type'    => 'in:note,password',
        ]);

        if ($errors) {
            Response::error('Dados inválidos.', 422, $errors);
        }

        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('
            INSERT INTO secrets (user_id, title, content, type, is_favorite)
            VALUES (:uid, :title, :content, :type, :fav)
        ');
        $stmt->execute([
            ':uid'     => $request->userId,
            ':title'   => trim($request->input('title')),
            ':content' => Crypto::encrypt($request->input('content')),
            ':type'    => $request->input('type', 'note'),
            ':fav'     => (int)(bool)$request->input('is_favorite', false),
        ]);

        $id     = (int) $pdo->lastInsertId();
        $secret = $pdo->prepare('SELECT id, title, type, is_favorite, created_at, updated_at FROM secrets WHERE id = :id');
        $secret->execute([':id' => $id]);

        Response::success($secret->fetch(), 'Segredo armazenado no cofre!', 201);
    }

    // GET /secrets/{id}
    public function show(Request $request, int $id): void
    {
        $secret = $this->findOwned($request->userId, $id);

        // Descriptografa o conteúdo apenas nesta rota
        $decrypted = Crypto::decrypt($secret['content']);
        if ($decrypted === false) {
            Response::error('Não foi possível descriptografar o segredo.', 500);
        }
        $secret['content'] = $decrypted;

        Response::success($secret, 'Segredo revelado.');
    }

    // PUT /secrets/{id}
    public function update(Request $request, int $id): void
    {
        $this->findOwned($request->userId, $id); // garante posse

        $errors = $request->validate([
            'title' => 'max:200',
        ]);
        if ($errors) {
            Response::error('Dados inválidos.', 422, $errors);
        }

        $pdo    = Database::getInstance();
        $fields = [];
        $params = [':id' => $id, ':uid' => $request->userId];

        if ($request->input('title') !== null) {
            $fields[]        = "title = :title";
            $params[':title'] = trim($request->input('title'));
        }
        if ($request->input('content') !== null) {
            $fields[]          = "content = :content";
            $params[':content'] = Crypto::encrypt($request->input('content'));
        }
        if ($request->input('type') !== null) {
            $allowed = ['note', 'password'];
            if (!in_array($request->input('type'), $allowed, true)) {
                Response::error("O campo 'type' deve ser 'note' ou 'password'.", 422);
            }
            $fields[]       = "type = :type";
            $params[':type'] = $request->input('type');
        }
        if ($request->input('is_favorite') !== null) {
            $fields[]      = "is_favorite = :fav";
            $params[':fav'] = (int)(bool)$request->input('is_favorite');
        }

        if (empty($fields)) {
            Response::error('Nenhum campo para atualizar foi fornecido.', 422);
        }

        $fields[] = "updated_at = datetime('now')";
        $sql      = 'UPDATE secrets SET ' . implode(', ', $fields) . ' WHERE id = :id AND user_id = :uid';
        $pdo->prepare($sql)->execute($params);

        $stmt = $pdo->prepare('SELECT id, title, type, is_favorite, created_at, updated_at FROM secrets WHERE id = :id');
        $stmt->execute([':id' => $id]);

        Response::success($stmt->fetch(), 'Segredo atualizado com sucesso.');
    }

    // DELETE /secrets/{id}
    public function destroy(Request $request, int $id): void
    {
        $this->findOwned($request->userId, $id);

        $pdo = Database::getInstance();
        $pdo->prepare('DELETE FROM secrets WHERE id = :id AND user_id = :uid')
            ->execute([':id' => $id, ':uid' => $request->userId]);

        Response::success(null, 'Segredo removido do cofre.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    private function findOwned(int $userId, int $id): array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM secrets WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $secret = $stmt->fetch();

        if (!$secret) {
            Response::notFound('Segredo não encontrado ou acesso negado.');
        }

        return $secret;
    }
}
