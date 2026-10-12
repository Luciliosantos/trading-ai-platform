<?php
declare(strict_types=1);

namespace App\Services;

use App\Connectors\BybitPublicConnector;
use App\Database;

class StrategyService
{
    public static function list(): array
    {
        $userId = \App\Controllers\AuthController::authenticatedUserId();
        if ($userId === null) {
            throw new \RuntimeException('Autenticação necessária.');
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, name, definition, status, created_at
             FROM strategies
             WHERE user_id = :uid
             ORDER BY id DESC'
        );
        $stmt->execute(['uid' => $userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function create(array $data): array
    {
        $userId = \App\Controllers\AuthController::authenticatedUserId();
        if ($userId === null) {
            throw new \RuntimeException('Autenticação necessária.');
        }

        $name = trim((string)($data['name'] ?? 'Nova estratégia'));
        if ($name === '') {
            $name = 'Nova estratégia';
        }
        if (mb_strlen($name) > 255) {
            throw new \InvalidArgumentException('Nome muito longo.');
        }

        $definition = [
            'market' => strtoupper((string)($data['market'] ?? 'BTCUSDT')),
            'timeframe' => (string)($data['timeframe'] ?? '5'),
            'entry' => $data['entry'] ?? [],
            'exit' => $data['exit'] ?? [],
            'risk' => $data['risk'] ?? [],
        ];

        $db = Database::connection();
        $stmt = $db->prepare(
            "INSERT INTO strategies (user_id, name, definition, status)
             VALUES (:uid, :name, CAST(:definition AS jsonb), 'paper')
             RETURNING id, name, definition, status, created_at"
        );
        $stmt->execute([
            'uid' => $userId,
            'name' => $name,
            'definition' => json_encode(
                $definition,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function backtest(array $data): array
    {
        $symbol = strtoupper(trim((string)($data['symbol'] ?? $data['market'] ?? 'BTCUSDT')));
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
        if ($initialBalance <= 0 || $feeRate < 0 || $feeRate > 0.1) {
            throw new \InvalidArgumentException('Saldo ou taxa inválidos.');
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
                'execution_allowed' => false,
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
