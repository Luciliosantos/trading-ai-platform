<?php
declare(strict_types=1);

namespace App\Services;

use App\Connectors\BybitPublicConnector;

class StrategyService
{
    private static string $file = '/tmp/trading_strategies.json';

    public static function list(): array
    {
        if (!file_exists(self::$file)) {
            return [];
        }

        $data = json_decode(file_get_contents(self::$file), true);
        return is_array($data) ? $data : [];
    }

    public static function create(array $data): array
    {
        $strategy = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $data['name'] ?? 'Nova estratégia',
            'market' => strtoupper($data['market'] ?? 'BTCUSDT'),
            'timeframe' => (string)($data['timeframe'] ?? '5'),
            'entry' => $data['entry'] ?? [],
            'exit' => $data['exit'] ?? [],
            'risk' => $data['risk'] ?? [],
            'status' => 'paper'
        ];

        $all = self::list();
        $all[] = $strategy;

        file_put_contents(
            self::$file,
            json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        return $strategy;
    }

    public static function backtest(array $data): array
    {
        $symbol = strtoupper((string)($data['symbol'] ?? $data['market'] ?? 'BTCUSDT'));
        $interval = (string)($data['timeframe'] ?? '5');
        $limit = (int)($data['limit'] ?? 200);
        $initialBalance = (float)($data['initial_balance'] ?? 1000);
        $feeRate = (float)($data['fee_rate'] ?? 0.001);
        $strategy = (string)($data['strategy'] ?? 'combined');

        if (!in_array($strategy, ['bollinger_rsi', 'ema_cross', 'combined'], true)) {
            throw new \InvalidArgumentException('Estratégia inválida.');
        }

        if (!preg_match('/^[A-Z0-9]{5,20}$/', $symbol)) {
            throw new \InvalidArgumentException('Símbolo inválido.');
        }

        if (!in_array($interval, ['1', '3', '5', '15', '30', '60'], true)) {
            throw new \InvalidArgumentException('Timeframe inválido.');
        }

        if ($limit < 30 || $limit > 200) {
            throw new \InvalidArgumentException('O limite deve ser de 30 a 200 candles.');
        }

        $connector = new BybitPublicConnector();
        $marketData = $connector->getCandles($symbol, $interval, $limit);

        if (
            !is_array($marketData)
            || ($marketData['status'] ?? '') !== 'ok'
            || !isset($marketData['candles'])
            || !is_array($marketData['candles'])
            || count($marketData['candles']) < 30
        ) {
            return [
                'status' => 'insufficient_data',
                'message' => 'A Bybit não retornou candles suficientes ou ocorreu um erro na consulta.',
                'market_response' => $marketData,
                'execution_allowed' => false
            ];
        }

        $result = BacktestService::run(
            $marketData['candles'],
            $initialBalance,
            $feeRate,
            $strategy
        );

        $result['strategy'] = $strategy;
        $result['symbol'] = $symbol;
        $result['timeframe'] = $interval;
        $result['mode'] = 'historical_simulation';
        $result['execution_allowed'] = false;

        return $result;
    }
}
