from math import isfinite


def ema(values, period):
    if len(values) < period:
        return None

    multiplier = 2 / (period + 1)
    result = sum(values[:period]) / period

    for value in values[period:]:
        result = (value * multiplier) + (result * (1 - multiplier))

    return result


def rsi(values, period=14):
    if len(values) <= period:
        return None

    changes = [
        values[i] - values[i - 1]
        for i in range(1, len(values))
    ]

    gains = [max(change, 0) for change in changes]
    losses = [max(-change, 0) for change in changes]

    average_gain = sum(gains[:period]) / period
    average_loss = sum(losses[:period]) / period

    for i in range(period, len(gains)):
        average_gain = (
            average_gain * (period - 1) + gains[i]
        ) / period
        average_loss = (
            average_loss * (period - 1) + losses[i]
        ) / period

    if average_loss == 0:
        return 100.0 if average_gain > 0 else 50.0

    relative_strength = average_gain / average_loss
    return 100 - (100 / (1 + relative_strength))


def analyze_market(data: dict) -> dict:
    candles = data.get("candles", [])

    if not isinstance(candles, list):
        candles = []

    try:
        closes = [
            float(candle["close"])
            for candle in candles
            if isinstance(candle, dict)
            and isfinite(float(candle["close"]))
            and float(candle["close"]) > 0
        ]
    except (KeyError, TypeError, ValueError):
        return {
            "status": "error",
            "reason": "Formato de candles inválido."
        }

    if len(closes) < 26:
        return {
            "status": "insufficient_data",
            "trend": "unknown",
            "ema9": None,
            "ema21": None,
            "rsi14": None,
            "signal": "HOLD",
            "reason": "São necessários pelo menos 26 fechamentos válidos."
        }

    fast = ema(closes, 9)
    slow = ema(closes, 21)
    momentum = rsi(closes, 14)
    price = closes[-1]

    if fast is None or slow is None or momentum is None:
        return {
            "status": "insufficient_data",
            "signal": "HOLD",
            "reason": "Não foi possível calcular todos os indicadores."
        }

    if fast > slow and 50 < momentum < 70:
        signal = "BUY"
        reason = "EMA 9 acima da EMA 21 e RSI confirma força compradora."
    elif fast < slow and 30 < momentum < 50:
        signal = "SELL"
        reason = "EMA 9 abaixo da EMA 21 e RSI confirma pressão vendedora."
    else:
        signal = "HOLD"
        reason = "Os indicadores não apresentam confirmação suficiente."

    return {
        "status": "ok",
        "last_close": price,
        "trend": "bullish" if fast > slow else (
            "bearish" if fast < slow else "neutral"
        ),
        "ema9": round(fast, 8),
        "ema21": round(slow, 8),
        "rsi14": round(momentum, 2),
        "signal": signal,
        "execution_allowed": False,
        "reason": reason
    }
