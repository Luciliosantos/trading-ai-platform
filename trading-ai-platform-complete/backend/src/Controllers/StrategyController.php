<?php
namespace App\Controllers;

use App\Services\StrategyService;

class StrategyController {
    public static function index(): void {
        \jsonResponse(StrategyService::list());
    }
    public static function create(): void {
        $data = \requestJson();
        \jsonResponse(StrategyService::create($data), 201);
    }
    public static function backtest(): void {
        $data = \requestJson();
        \jsonResponse(StrategyService::backtest($data));
    }
}
