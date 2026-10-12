<?php
namespace App;

use App\Controllers\AuthController;

class Router {
    private array $routes = ['GET' => [], 'POST' => []];

    private static array $publicRoutes = [
        'GET /api/health',
        'POST /api/auth/register',
        'POST /api/auth/login',
        'GET /api/market/ticker',
        'GET /api/market/analysis',
        'GET /api/market/signals',
    ];

    public function get(string $path, callable $handler): void {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $path): void {
        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler) {
            \jsonResponse(['error' => 'not_found'], 404);
            return;
        }

        if (!in_array($method . ' ' . $path, self::$publicRoutes, true)) {
            try {
                if (AuthController::authenticatedUserId() === null) {
                    \jsonResponse([
                        'error' => 'unauthorized',
                        'message' => 'Faça login para continuar.'
                    ], 401);
                    return;
                }
            } catch (\Throwable $e) {
                \jsonResponse(['error' => 'authentication_unavailable'], 503);
                return;
            }
        }

        $handler();
    }
}
