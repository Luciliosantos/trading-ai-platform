<?php
declare(strict_types=1);

namespace App\Services;

use App\Connectors\TechnicalAnalysis;

final class BacktestService
{
    public static function run(
        array $candles,
        float $initialBalance = 1000.0,
        float $feeRate = 0.001,
        string $strategy = 'combined'
    ): array {
        if ($initialBalance <= 0 || $feeRate < 0 || $feeRate > 0.05) {
            throw new \InvalidArgumentException('Saldo ou taxa inválidos.');
        }

        $candles = array_values(array_filter($candles, static function ($c) {
            return is_array($c)
                && isset($c['timestamp'], $c['close'])
                && is_numeric($c['timestamp'])
                && is_numeric($c['close'])
                && (float)$c['close'] > 0;
        }));

        usort($candles, static fn($a, $b) =>
            (int)$a['timestamp'] <=> (int)$b['timestamp']
        );

        if (count($candles) < 30) {
            return [
                'status' => 'insufficient_data',
                'message' => 'São necessários pelo menos 30 candles válidos.',
                'execution_allowed' => false
            ];
        }

        $cash = $initialBalance;
        $position = null;
        $trades = [];
        $wins = 0;
        $losses = 0;
        $peakEquity = $initialBalance;
        $maxDrawdown = 0.0;

        for ($i = 26; $i < count($candles) - 1; $i++) {
            $history = array_slice($candles, 0, $i + 1);
            $analysis = TechnicalAnalysis::analyze($history, $strategy);

            if (($analysis['status'] ?? '') !== 'ok') {
                continue;
            }

            // O sinal usa candles já fechados.
            // A simulação executa na abertura do candle seguinte.
            $next = $candles[$i + 1];
            $price = isset($next['open']) && (float)$next['open'] > 0
                ? (float)$next['open']
                : (float)$next['close'];

            $signal = $analysis['signal'] ?? 'HOLD';

            // Spot: BUY abre posição; SELL fecha posição.
            // Nunca abre uma posição vendida sem margem.
            if ($position === null && $signal === 'BUY' && $cash > 0) {
                $entryNotional = $cash / (1.0 + $feeRate);
                $entryFee = $entryNotional * $feeRate;
                $quantity = $entryNotional / $price;

                $position = [
                    'entry_price' => $price,
                    'entry_timestamp' => (int)$next['timestamp'],
                    'quantity' => $quantity,
                    'entry_fee' => $entryFee,
                    'invested' => $entryNotional + $entryFee
                ];

                $cash = 0.0;
            } elseif ($position !== null && $signal === 'SELL') {
                $grossProceeds = $position['quantity'] * $price;
                $exitFee = $grossProceeds * $feeRate;
                $cash = $grossProceeds - $exitFee;

                $grossPnl = ($price - $position['entry_price'])
                    * $position['quantity'];

                $totalFees = $position['entry_fee'] + $exitFee;
                $netPnl = $grossPnl - $totalFees;

                if ($netPnl > 0) {
                    $wins++;
                } else {
                    $losses++;
                }

                $trades[] = [
                    'side' => 'BUY',
                    'entry_price' => round($position['entry_price'], 8),
                    'exit_price' => round($price, 8),
                    'gross_pnl' => round($grossPnl, 8),
                    'fees' => round($totalFees, 8),
                    'net_pnl' => round($netPnl, 8),
                    'result' => $netPnl > 0 ? 'WIN' : 'LOSS',
                    'entry_timestamp' => $position['entry_timestamp'],
                    'exit_timestamp' => (int)$next['timestamp']
                ];

                $position = null;
            }

            $markPrice = (float)$next['close'];
            $equity = $position === null
                ? $cash
                : $position['quantity'] * $markPrice * (1.0 - $feeRate);

            $peakEquity = max($peakEquity, $equity);
            if ($peakEquity > 0) {
                $drawdown = (($peakEquity - $equity) / $peakEquity) * 100;
                $maxDrawdown = max($maxDrawdown, $drawdown);
            }
        }

        // Marca a posição ainda aberta pelo preço do último candle,
        // descontando uma taxa estimada para encerramento.
        $openPosition = $position !== null;
        $unrealizedPnl = 0.0;
        $finalBalance = $cash;

        if ($openPosition) {
            $last = $candles[count($candles) - 1];
            $lastPrice = (float)$last['close'];
            $finalBalance = $position['quantity'] * $lastPrice * (1.0 - $feeRate);
            $unrealizedPnl = $finalBalance - $position['invested'];
        }

        $closedTrades = count($trades);
        $netResult = $finalBalance - $initialBalance;

        return [
            'status' => 'ok',
            'mode' => 'backtest_only',
            'execution_allowed' => false,
            'candles_tested' => count($candles),
            'initial_balance' => round($initialBalance, 2),
            'final_balance' => round($finalBalance, 2),
            'net_result' => round($netResult, 2),
            'return_percent' => round(($netResult / $initialBalance) * 100, 4),
            'closed_trades' => $closedTrades,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate_percent' => $closedTrades > 0
                ? round(($wins / $closedTrades) * 100, 2)
                : 0.0,
            'max_drawdown_percent' => round($maxDrawdown, 4),
            'fee_rate_per_side' => $feeRate,
            'open_position_at_end' => $openPosition,
            'unrealized_pnl' => round($unrealizedPnl, 2),
            'trades' => $trades
        ];
    }
}
