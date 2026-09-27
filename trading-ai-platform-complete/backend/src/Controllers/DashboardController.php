<?php
namespace App\Controllers;
class DashboardController {
    public static function show(): void {
        \jsonResponse([
            'mode'=>getenv('TRADING_MODE') ?: 'paper',
            'live_enabled'=>filter_var(getenv('ENABLE_LIVE_TRADING'), FILTER_VALIDATE_BOOL),
            'stats'=>[
                'strategies'=>0,
                'active_positions'=>0,
                'paper_pnl'=>0,
                'subscription'=>'trial'
            ]
        ]);
    }
}
