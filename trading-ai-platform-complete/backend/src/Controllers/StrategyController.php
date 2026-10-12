<?php
namespace App\Controllers;

use App\Services\StrategyService;
use App\Controllers\AuthController;

class StrategyController {
    public static function index(): void {
        if (AuthController::authenticatedUserId() === null) {
            \jsonResponse(['status' => 'error', 'message' => 'Faça login para continuar.'], 401);
            return;
        }
        \jsonResponse(StrategyService::list());
    }
    public static function create(): void {
        if (AuthController::authenticatedUserId() === null) {
            jsonResponse(['status' => 'error', 'message' => 'Faça login para continuar.'], 401);
            return;
        }


        $data = \requestJson();
        \jsonResponse(StrategyService::create($data), 201);
    }
    public static function backtest(): void {
        if (AuthController::authenticatedUserId() === null) {
            jsonResponse(['status' => 'error', 'message' => 'Faça login para continuar.'], 401);
            return;
        }


        $data = \requestJson();
        \jsonResponse(StrategyService::backtest($data));
    }
}
