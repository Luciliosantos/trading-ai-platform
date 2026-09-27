<?php
namespace App\Controllers;

class SubscriptionController {
    public static function show(): void {
        \jsonResponse([
            'plan'=>'trial',
            'status'=>'active',
            'expires_at'=>null,
            'features'=>['paper','backtest','telegram']
        ]);
    }
    public static function webhook(): void {
        // Payment providers are intentionally adapter-based.
        \jsonResponse(['received'=>true]);
    }
}
