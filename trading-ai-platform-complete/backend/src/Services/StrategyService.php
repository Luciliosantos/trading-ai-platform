<?php
namespace App\Services;

class StrategyService {
    private static string $file='/tmp/trading_strategies.json';

    public static function list(): array {
        if (!file_exists(self::$file)) return [];
        return json_decode(file_get_contents(self::$file), true) ?: [];
    }

    public static function create(array $data): array {
        $strategy=[
            'id'=>bin2hex(random_bytes(8)),
            'name'=>$data['name']??'Nova estratégia',
            'market'=>$data['market']??'BTCUSDT',
            'timeframe'=>$data['timeframe']??'5m',
            'entry'=>$data['entry']??[],
            'exit'=>$data['exit']??[],
            'risk'=>$data['risk']??[],
            'status'=>'paper'
        ];
        $all=self::list(); $all[]=$strategy;
        file_put_contents(self::$file,json_encode($all,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        return $strategy;
    }

    public static function backtest(array $data): array {
        return [
            'status'=>'simulated',
            'message'=>'Backtest engine base. Conecte dados históricos antes de usar resultados para decisão.',
            'strategy'=>$data,
            'metrics'=>['trades'=>0,'win_rate'=>0,'profit_factor'=>0,'max_drawdown'=>0]
        ];
    }
}
