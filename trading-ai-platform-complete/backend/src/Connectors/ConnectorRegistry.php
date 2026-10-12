<?php
namespace App\Connectors;

class ConnectorRegistry {
    public static function all(): array {
        return [
            'paper' => PaperConnector::class,
            'binance' => null,
            'bybit' => \\App\\Connectors\\BybitPublicConnector::class,
            'b3' => null,
            'iqoption' => null,
            'expertoption' => null,
        ];
    }
}
