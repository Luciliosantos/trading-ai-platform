<?php
namespace App\Connectors;

use App\Contracts\TradingConnector;

class PaperConnector implements TradingConnector {
    public function name(): string { return 'paper'; }
    public function capabilities(): array {
        return ['balance'=>true,'ticker'=>true,'positions'=>true,'orders'=>true,'cancel'=>true];
    }
    public function getBalance(): array { return ['currency'=>'USDT','available'=>10000.0]; }
    public function getTicker(string $symbol): array { return ['symbol'=>$symbol,'price'=>0.0]; }
    public function getPositions(): array { return []; }
    public function placeOrder(array $order): array {
        return ['status'=>'simulated','id'=>'PAPER-'.bin2hex(random_bytes(6)),'order'=>$order];
    }
    public function cancelOrder(string $orderId): array {
        return ['status'=>'simulated_cancel','order_id'=>$orderId];
    }
    public function closePosition(string $symbol): array {
        return ['status'=>'simulated_close','symbol'=>$symbol];
    }
}
