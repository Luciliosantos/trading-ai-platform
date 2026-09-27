<?php
namespace App\Controllers;
class HealthController {
    public static function health(): void {
        \jsonResponse(['status'=>'ok','service'=>'trading-ai-api','mode'=>getenv('TRADING_MODE') ?: 'paper']);
    }
}
