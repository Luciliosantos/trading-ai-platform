<?php
namespace App\Controllers;

use App\Database;
use App\Connectors\PaperConnector;
use App\Risk\RiskManager;
use App\Trading\OrderManager;
use PDO;

class TradingController {

    public static function ticker(): void {
        $symbol = $_GET['symbol'] ?? 'BTCUSDT';

        $connector = new \App\Connectors\BybitPublicConnector();
        \jsonResponse($connector->getTicker($symbol));
    }

    public static function positions(): void {
        $db = Database::connection();

        $stmt = $db->query("
            SELECT id, connector, symbol, side, quantity, price,
                   status, external_id, created_at
            FROM orders
            WHERE connector = 'paper'
            ORDER BY created_at DESC
            LIMIT 100
        ");

        \jsonResponse([
            'mode' => 'paper',
            'positions' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    public static function simulate(): void {
        $data = \requestJson();

        $symbol = strtoupper(trim((string)($data['symbol'] ?? '')));
        $side = strtoupper(trim((string)($data['side'] ?? '')));
        $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
        $price = filter_var($data['price'] ?? null, FILTER_VALIDATE_FLOAT);

        if (
            $symbol === '' ||
            !preg_match('/^[A-Z0-9_-]{2,30}$/', $symbol) ||
            !in_array($side, ['BUY', 'SELL'], true) ||
            $quantity === false || $quantity === null ||
            $price === false || $price === null ||
            !is_finite((float)$quantity) ||
            !is_finite((float)$price) ||
            $quantity <= 0 || $price <= 0
        ) {
            \jsonResponse([
                'status' => 'error',
                'message' => 'Informe símbolo, BUY ou SELL, quantidade e preço válidos.'
            ]);
            return;
        }

        $data['symbol'] = $symbol;
        $data['side'] = $side;
        $data['quantity'] = (float)$quantity;
        $data['price'] = (float)$price;

        $connector = new PaperConnector();
        $manager = new OrderManager($connector, new RiskManager());

        $result = $manager->submit($data, [
            'max_order_value' => 1000
        ]);

        if (($result['status'] ?? '') !== 'simulated') {
            \jsonResponse($result);
            return;
        }

        $db = Database::connection();

        $stmt = $db->prepare("
            INSERT INTO orders
                (user_id, connector, symbol, side, quantity, price,
                 status, external_id, metadata)
            VALUES
                (NULL, 'paper', :symbol, :side, :quantity, :price,
                 'simulated', :external_id, CAST(:metadata AS JSONB))
            RETURNING id
        ");

        $stmt->execute([
            ':symbol' => $symbol,
            ':side' => $side,
            ':quantity' => $data['quantity'],
            ':price' => $data['price'],
            ':external_id' => $result['id'] ?? null,
            ':metadata' => json_encode(
                ['mode' => 'paper', 'simulation' => true],
                JSON_UNESCAPED_UNICODE
            )
        ]);

        $result['database_id'] = $stmt->fetchColumn();
        $result['mode'] = 'paper';
        $result['message'] = 'Ordem simulada e registrada no banco. Nenhuma ordem real foi enviada.';

        \jsonResponse($result);
    }

    public static function killSwitch(): void {
        file_put_contents('/tmp/trading_kill_switch', date('c'));

        \jsonResponse([
            'status' => 'kill_switch_active',
            'message' => 'Novas simulações bloqueadas.'
        ]);
    }

    public static function analysis(): void {
        $symbol = strtoupper(trim((string)($_GET['symbol'] ?? 'BTCUSDT')));

        if (!preg_match('/^[A-Z0-9]{3,30}$/', $symbol)) {
            \jsonResponse([
                'status' => 'error',
                'message' => 'Símbolo inválido.'
            ]);
            return;
        }

        $connector = new \App\Connectors\BybitPublicConnector();
        $result = $connector->getCandles($symbol, '1', 100);

        if (($result['status'] ?? '') !== 'ok') {
            \jsonResponse($result);
            return;
        }

        $candles = $result['candles'];
        $market = \App\Connectors\TechnicalAnalysis::analyze($candles);

        if (($market['status'] ?? '') === 'ok' && !empty($candles)) {
            $lastCandle = end($candles);

            try {
                $db = Database::connection();

                $candleTimestamp = (int)($lastCandle['timestamp'] ?? 0);

                if ($candleTimestamp <= 0) {
                    throw new \RuntimeException(
                        'O candle não contém um timestamp válido.'
                    );
                }

                $candleTime = gmdate(
                    'Y-m-d H:i:s',
                    (int)floor($candleTimestamp / 1000)
                );

                $stmt = $db->prepare("
                    INSERT INTO signal_history
                        (symbol, timeframe, candle_time, price, trend,
                         ema9, ema21, rsi14, signal, reason)
                    VALUES
                        (:symbol, '1m', :candle_time, :price, :trend,
                         :ema9, :ema21, :rsi14, :signal, :reason)
                    ON CONFLICT (symbol, timeframe, candle_time)
                    DO UPDATE SET
                        price = EXCLUDED.price,
                        trend = EXCLUDED.trend,
                        ema9 = EXCLUDED.ema9,
                        ema21 = EXCLUDED.ema21,
                        rsi14 = EXCLUDED.rsi14,
                        signal = EXCLUDED.signal,
                        reason = EXCLUDED.reason
                ");

                $stmt->execute([
                    ':symbol' => $symbol,
                    ':candle_time' => $candleTime,
                    ':price' => $market['last_close'],
                    ':trend' => $market['trend'],
                    ':ema9' => $market['ema9'],
                    ':ema21' => $market['ema21'],
                    ':rsi14' => $market['rsi14'],
                    ':signal' => $market['signal'],
                    ':reason' => $market['reason']
                ]);
            } catch (\Throwable $e) {
                error_log('Falha ao salvar histórico de sinais: ' . $e->getMessage());
            }
        }

        \jsonResponse([
            'status' => 'ok',
            'symbol' => $symbol,
            'exchange' => 'bybit',
            'interval' => '1m',
            'candles_count' => $result['count'],
            'market' => $market,
            'execution_allowed' => false,
            'mode' => 'analysis_only'
        ]);
    }


    public static function signalHistory(): void {
        $symbol = strtoupper(trim((string)($_GET['symbol'] ?? 'BTCUSDT')));
        $limit = (int)($_GET['limit'] ?? 20);
        $limit = max(1, min($limit, 100));

        if (!preg_match('/^[A-Z0-9]{3,30}$/', $symbol)) {
            \jsonResponse([
                'status' => 'error',
                'message' => 'Símbolo inválido.'
            ]);
            return;
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare("
                SELECT id, symbol, timeframe, candle_time,
                       price, trend, ema9, ema21, rsi14,
                       signal, reason, created_at
                FROM signal_history
                WHERE symbol = :symbol
                ORDER BY candle_time DESC, id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':symbol', $symbol, PDO::PARAM_STR);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            \jsonResponse([
                'status' => 'ok',
                'symbol' => $symbol,
                'signals' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
        } catch (\Throwable $e) {
            error_log('Falha ao consultar histórico: ' . $e->getMessage());
            \jsonResponse([
                'status' => 'error',
                'message' => 'Não foi possível consultar o histórico.'
            ]);
        }
    }

}
