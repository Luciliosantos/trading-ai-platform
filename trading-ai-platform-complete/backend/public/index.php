<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Router;
use App\Controllers\HealthController;
use App\Controllers\DashboardController;
use App\Controllers\StrategyController;
use App\Controllers\RiskController;
use App\Controllers\SubscriptionController;
use App\Controllers\TradingController;

$router = new Router();

$router->get('/api/health', fn() => HealthController::health());
$router->get('/api/dashboard', fn() => DashboardController::show());
$router->get('/api/strategies', fn() => StrategyController::index());
$router->post('/api/strategies', fn() => StrategyController::create());
$router->post('/api/backtest', fn() => StrategyController::backtest());
$router->get('/api/risk', fn() => RiskController::show());
$router->post('/api/risk', fn() => RiskController::update());
$router->get('/api/subscription', fn() => SubscriptionController::show());
$router->post('/api/subscription/webhook', fn() => SubscriptionController::webhook());
$router->get('/api/positions', fn() => TradingController::positions());
$router->post('/api/simulate/order', fn() => TradingController::simulate());
$router->post('/api/kill-switch', fn() => TradingController::killSwitch());

$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
