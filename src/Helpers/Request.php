<?php

declare(strict_types=1);

namespace App\Helpers;

class Request
{
    public readonly string $method;
    public readonly string $path;
    private array $body;
    public ?int $userId = null;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $raw = file_get_contents('php://input');
        $this->body = json_decode($raw ?: '{}', true) ?? [];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->body, array_flip($keys));
    }

    public function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }

    public function validate(array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $value = $this->input($field);
            foreach (explode('|', $rule) as $r) {
                if ($r === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = "O campo '$field' é obrigatório.";
                }
                if (str_starts_with($r, 'min:')) {
                    $min = (int) substr($r, 4);
                    if (is_string($value) && \mb_strlen($value) < $min) {
                        $errors[$field][] = "O campo '$field' deve ter ao menos $min caracteres.";
                    }
                }
                if (str_starts_with($r, 'max:')) {
                    $max = (int) substr($r, 4);
                    if (is_string($value) && \mb_strlen($value) > $max) {
                        $errors[$field][] = "O campo '$field' deve ter no máximo $max caracteres.";
                    }
                }
                if ($r === 'email' && $value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "O campo '$field' deve ser um e-mail válido.";
                }
                if ($r === 'in:note,password' && $value !== null && !in_array($value, ['note', 'password'], true)) {
                    $errors[$field][] = "O campo '$field' deve ser 'note' ou 'password'.";
                }
            }
        }
        return $errors;
    }
}
