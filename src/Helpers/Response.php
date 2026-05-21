<?php

declare(strict_types=1);

namespace App\Helpers;

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Sucesso', int $status = 200): void
    {
        self::json([
            'error'   => false,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): void
    {
        $payload = [
            'error'   => true,
            'message' => $message,
        ];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        self::json($payload, $status);
    }

    public static function notFound(string $message = 'Recurso não encontrado.'): void
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Não autorizado.'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Acesso negado.'): void
    {
        self::error($message, 403);
    }
}
