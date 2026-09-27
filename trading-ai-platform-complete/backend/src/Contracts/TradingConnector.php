<?php
namespace App\Contracts;

interface TradingConnector {
    public function name(): string;
    public function capabilities(): array;
    public function getBalance(): array;
    public function getTicker(string $symbol): array;
    public function getPositions(): array;
    public function placeOrder(array $order): array;
    public function cancelOrder(string $orderId): array;
    public function closePosition(string $symbol): array;
}
