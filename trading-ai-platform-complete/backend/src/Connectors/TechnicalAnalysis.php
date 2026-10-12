<?php
declare(strict_types=1);

namespace App\Connectors;

final class TechnicalAnalysis
{
    private static function ema(array $values, int $period): float
    {
        $alpha = 2 / ($period + 1);
        $ema = (float)$values[0];

        foreach (array_slice($values, 1) as $value) {
            $ema = ((float)$value * $alpha)
                 + ($ema * (1 - $alpha));
        }

        return $ema;
    }

    private static function rsi(array $closes, int $period = 14): float
    {
        $count = count($closes);

        if ($count <= $period) {
            return 50.0;
        }

        $gains = 0.0;
        $losses = 0.0;

        for ($i = $count - $period; $i < $count; $i++) {
            $change = (float)$closes[$i]
                    - (float)$closes[$i - 1];

            if ($change > 0) {
                $gains += $change;
            } else {
                $losses += abs($change);
            }
        }

        if ($losses == 0.0) {
            return $gains > 0 ? 100.0 : 50.0;
        }

        $rs = ($gains / $period) / ($losses / $period);

        return 100 - (100 / (1 + $rs));
    }

    private static function bollinger(array $closes): array
    {
        $period = 20;
        $values = array_slice($closes, -$period);
        $middle = array_sum($values) / $period;
        $variance = 0.0;

        foreach ($values as $value) {
            $variance += ((float)$value - $middle) ** 2;
        }

        $deviation = sqrt($variance / $period);

        return [
            'middle' => $middle,
            'upper' => $middle + (2 * $deviation),
            'lower' => $middle - (2 * $deviation),
            'width_percent' => $middle != 0.0
                ? (($deviation * 4) / $middle) * 100
                : 0.0,
        ];
    }

    public static function analyze(array $candles, string $strategy = 'combined'): array
    {
        $candles = array_values(array_filter(
            $candles,
            static fn($c) =>
                is_array($c)
                && isset($c['close'])
                && is_numeric($c['close'])
                && is_finite((float)$c['close'])
                && (float)$c['close'] > 0
        ));

        if (count($candles) < 26) {
            return [
                'status' => 'insufficient_data',
                'reason' => 'São necessários pelo menos 26 candles.',
                'execution_allowed' => false,
            ];
        }

        $closes = array_map(
            static fn($c) => (float)$c['close'],
            $candles
        );

        $last = count($closes) - 1;
        $price = $closes[$last];

        $ema9 = self::ema(array_slice($closes, -26), 9);
        $ema21 = self::ema(array_slice($closes, -26), 21);
        $rsi = self::rsi($closes, 14);
        $bb = self::bollinger($closes);

        $previousCloses = array_slice($closes, 0, -1);
        $previousBb = self::bollinger($previousCloses);

        $previousPrice = $closes[$last - 1];

        $crossedAboveLower =
            $previousPrice <= $previousBb['lower']
            && $price > $bb['lower'];

        $crossedBelowUpper =
            $previousPrice >= $previousBb['upper']
            && $price < $bb['upper'];

        $trend = $ema9 > $ema21
            ? 'bullish'
            : ($ema9 < $ema21 ? 'bearish' : 'neutral');

        $signal = 'HOLD';
        $reason = 'Aguardando confirmação dos indicadores.';

        if (!in_array($strategy, ['bollinger_rsi', 'ema_cross', 'combined'], true)) {
            throw new \InvalidArgumentException('Estratégia inválida.');
        }

        $previousEma9 = self::ema(array_slice($closes, -27, 26), 9);
        $previousEma21 = self::ema(array_slice($closes, -27, 26), 21);

        $nearLower = $price <= $bb['lower'] * 1.003;
        $nearUpper = $price >= $bb['upper'] * 0.997;

        $buyBollinger = $nearLower && $rsi < 40;
        $sellBollinger = $nearUpper && $rsi > 60;

        $buyCross = $previousEma9 <= $previousEma21 && $ema9 > $ema21;
        $sellCross = $previousEma9 >= $previousEma21 && $ema9 < $ema21;

        $signal = 'HOLD';
        $reason = 'Nenhuma condição de entrada confirmada.';

        if ($strategy === 'bollinger_rsi') {
            if ($buyBollinger) {
                $signal = 'BUY';
                $reason = 'Bollinger inferior e RSI indicando possível recuperação.';
            } elseif ($sellBollinger) {
                $signal = 'SELL';
                $reason = 'Bollinger superior e RSI indicando possível reversão.';
            }
        } elseif ($strategy === 'ema_cross') {
            if ($buyCross) {
                $signal = 'BUY';
                $reason = 'Cruzamento de alta da EMA 9 sobre a EMA 21.';
            } elseif ($sellCross) {
                $signal = 'SELL';
                $reason = 'Cruzamento de baixa da EMA 9 sob a EMA 21.';
            }
        } else {
            if ($buyBollinger && $ema9 > $ema21) {
                $signal = 'BUY';
                $reason = 'Bollinger, RSI e tendência EMA confirmam compra.';
            } elseif ($sellBollinger && $ema9 < $ema21) {
                $signal = 'SELL';
                $reason = 'Bollinger, RSI e tendência EMA confirmam venda.';
            }
        }

        return [
            'status' => 'ok',
            'last_close' => $price,
            'trend' => $trend,
            'ema9' => $ema9,
            'ema21' => $ema21,
            'rsi14' => $rsi,
            'bollinger_upper' => $bb['upper'],
            'bollinger_middle' => $bb['middle'],
            'bollinger_lower' => $bb['lower'],
            'bollinger_width_percent' => $bb['width_percent'],
            'signal' => $signal,
            'execution_allowed' => false,
            'reason' => $reason,
        ];
    }
}
