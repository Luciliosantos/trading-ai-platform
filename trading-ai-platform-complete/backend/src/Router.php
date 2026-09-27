<?php
namespace App;

class Router {
    private array $routes = ['GET'=>[], 'POST'=>[]];
    public function get(string $path, callable $handler): void { $this->routes['GET'][$path] = $handler; }
    public function post(string $path, callable $handler): void { $this->routes['POST'][$path] = $handler; }
    public function dispatch(string $method, string $path): void {
        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) \jsonResponse(['error'=>'not_found'], 404);
        $handler();
    }
}
