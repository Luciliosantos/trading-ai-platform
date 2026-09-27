<?php
namespace App\Trading;

use App\Contracts\TradingConnector;
use App\Risk\RiskManager;

class OrderManager {
    public function __construct(private TradingConnector $connector, private RiskManager $risk) {}
    public function submit(array $order, array $limits): array {
        $decision=$this->risk->approve($order,$limits);
        if (!$decision['approved']) return ['status'=>'blocked','reason'=>$decision['reason']];
        return $this->connector->placeOrder($order);
    }
}
