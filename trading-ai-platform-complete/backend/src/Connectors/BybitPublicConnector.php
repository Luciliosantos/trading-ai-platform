<?php
namespace App\Connectors;

class BybitPublicConnector
{
    private string $baseUrl = 'https://api.bybit.com';

    public function getTicker(string $symbol = 'BTCUSDT'): array
    {
        $symbol = strtoupper(trim($symbol));

        if (!preg_match('/^[A-Z0-9]{3,30}$/', $symbol)) {
            return [
                'status' => 'error',
                'message' => 'Símbolo inválido.'
            ];
        }

        $url = $this->baseUrl
            . '/v5/market/tickers?category=spot&symbol='
            . rawurlencode($symbol);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "Accept: application/json\r\n"
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return [
                'status' => 'error',
                'message' => 'Não foi possível consultar a Bybit.'
            ];
        }

        $data = json_decode($response, true);

        if (
            !is_array($data) ||
            ($data['retCode'] ?? -1) !== 0 ||
            empty($data['result']['list'][0])
        ) {
            return [
                'status' => 'error',
                'message' => 'A Bybit não retornou dados para esse ativo.'
            ];
        }

        $ticker = $data['result']['list'][0];

        return [
            'status' => 'ok',
            'exchange' => 'bybit',
            'category' => 'spot',
            'symbol' => $ticker['symbol'],
            'last_price' => $ticker['lastPrice'],
            'bid' => $ticker['bid1Price'],
            'ask' => $ticker['ask1Price'],
            'volume_24h' => $ticker['volume24h'],
            'change_24h_percent' => $ticker['price24hPcnt'],
            'timestamp' => $data['time'] ?? null
        ];
    }

    public function getCandles(
        string $symbol = 'BTCUSDT',
        string $interval = '1',
        int $limit = 100
    ): array {
        $symbol = strtoupper(trim($symbol));

        if (
            !preg_match('/^[A-Z0-9]{3,30}$/', $symbol) ||
            !in_array($interval, ['1', '3', '5', '15', '30', '60'], true)
        ) {
            return [
                'status' => 'error',
                'message' => 'Símbolo ou intervalo inválido.'
            ];
        }

        $limit = max(26, min($limit, 200));

        $url = $this->baseUrl
            . '/v5/market/kline?category=spot'
            . '&symbol=' . rawurlencode($symbol)
            . '&interval=' . rawurlencode($interval)
            . '&limit=' . $limit;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'header' => "Accept: application/json\r\n"
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return [
                'status' => 'error',
                'message' => 'Não foi possível consultar os candles da Bybit.'
            ];
        }

        $data = json_decode($response, true);

        if (
            !is_array($data) ||
            (int)($data['retCode'] ?? -1) !== 0 ||
            !isset($data['result']['list']) ||
            !is_array($data['result']['list'])
        ) {
            return [
                'status' => 'error',
                'message' => 'A Bybit não retornou candles válidos.'
            ];
        }

        $candles = [];

        foreach ($data['result']['list'] as $row) {
            if (!is_array($row) || count($row) < 6) {
                continue;
            }

            $close = filter_var($row[4], FILTER_VALIDATE_FLOAT);

            if ($close === false || !is_finite((float)$close) || $close <= 0) {
                continue;
            }

            $candles[] = [
                'timestamp' => (int)$row[0],
                'open' => (float)$row[1],
                'high' => (float)$row[2],
                'low' => (float)$row[3],
                'close' => (float)$close,
                'volume' => (float)$row[5]
            ];
        }

        // A Bybit retorna os candles do mais recente para o mais antigo.
        $candles = array_reverse($candles);

        return [
            'status' => 'ok',
            'exchange' => 'bybit',
            'category' => 'spot',
            'symbol' => $symbol,
            'interval' => $interval,
            'count' => count($candles),
            'candles' => $candles
        ];
    }

}
