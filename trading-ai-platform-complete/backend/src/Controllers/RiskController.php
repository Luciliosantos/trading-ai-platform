<?php
namespace App\Controllers;

class RiskController {
    public static function show(): void {
        \jsonResponse([
            'max_order_value'=>100,
            'max_daily_loss'=>300,
            'max_open_positions'=>3,
            'live_enabled'=>false
        ]);
    }
    public static function update(): void {
        $data = \requestJson();
        \jsonResponse(['status'=>'saved','risk'=>$data]);
    }
}
