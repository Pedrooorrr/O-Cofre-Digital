<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/config/bootstrap.php';

use App\Helpers\Router;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Controllers\AuthController;
use App\Controllers\SecretController;

// Headers CORS e JSON
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();
$request = new Request();

// ── Rotas Públicas ──────────────────────────────────────────
$router->post('/auth/register', function () use ($request) {
    (new AuthController())->register($request);
});

$router->post('/auth/login', function () use ($request) {
    (new AuthController())->login($request);
});

// ── Rotas Protegidas ────────────────────────────────────────
$router->get('/secrets', function () use ($request) {
    AuthMiddleware::handle($request);
    (new SecretController())->index($request);
});

$router->post('/secrets', function () use ($request) {
    AuthMiddleware::handle($request);
    (new SecretController())->store($request);
});

$router->get('/secrets/{id}', function (array $params) use ($request) {
    AuthMiddleware::handle($request);
    (new SecretController())->show($request, (int)$params['id']);
});

$router->put('/secrets/{id}', function (array $params) use ($request) {
    AuthMiddleware::handle($request);
    (new SecretController())->update($request, (int)$params['id']);
});

$router->delete('/secrets/{id}', function (array $params) use ($request) {
    AuthMiddleware::handle($request);
    (new SecretController())->destroy($request, (int)$params['id']);
});

// ── Dispatch ────────────────────────────────────────────────
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$router->dispatch($method, $path);
