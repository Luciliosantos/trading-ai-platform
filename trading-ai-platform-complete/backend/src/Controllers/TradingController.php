<?php
namespace App\Controllers;

use App\Connectors\PaperConnector;
use App\Risk\RiskManager;
use App\Trading\OrderManager;

class TradingController {
    public static function positions(): void {
        \jsonResponse(['positions'=>[]]);
    }

    public static function simulate(): void {
        $data = \requestJson();
        $connector = new PaperConnector();
        $manager = new OrderManager($connector, new RiskManager());
        \jsonResponse($manager->submit($data, [
            'max_order_value'=>1000
        ]));
    }

    public static function killSwitch(): void {
        file_put_contents('/tmp/trading_kill_switch', date('c'));
        \jsonResponse(['status'=>'kill_switch_active']);
    }
}
