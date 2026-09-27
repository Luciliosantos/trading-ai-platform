<?php
namespace App\Risk;

class RiskManager {
    public function approve(array $order, array $limits): array {
        if (file_exists('/tmp/trading_kill_switch')) {
            return ['approved'=>false,'reason'=>'kill_switch'];
        }
        $qty=(float)($order['quantity']??0);
        $price=(float)($order['price']??0);
        $value=$qty*$price;
        $max=(float)($limits['max_order_value']??0);
        if ($max>0 && $value>$max) return ['approved'=>false,'reason'=>'order_value_limit'];
        return ['approved'=>true,'reason'=>null];
    }
}
