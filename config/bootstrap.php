<?php

declare(strict_types=1);

// Autoloader simples (sem Composer)
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $base   = ROOT_PATH . '/src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Carrega variáveis de ambiente do .env
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $value;
    }
}

// Timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'America/Sao_Paulo');

// Handler de erros global
set_exception_handler(function (Throwable $e): void {
    $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
    http_response_code($code);
    echo json_encode([
        'error'   => true,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
