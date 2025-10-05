<?php

namespace William\HyperfExtTelegram\Helper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use Hyperf\Cache\Cache;
use function Hyperf\Support\make;
use function Hyperf\Translation\trans;

class BotHelper
{
    public static function getTrxUsdtPrice(): string
    {
        $url = 'https://api.binance.com/api/v3/ticker/price?symbol=TRXUSDT';

        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get($url);
            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['price'])) {
                throw new \RuntimeException('Missing price in response');
            }

            return $data['price'];
        } catch (RequestException $e) {
            throw new \RuntimeException('Request failed: ' . $e->getMessage());
        } catch (\Throwable $e) {
            throw new \RuntimeException('Parsing failed: ' . $e->getMessage());
        }
    }

    public static function usdtToTrx(float|string $usdtAmount): float
    {
        $rate = (float)self::getTrxUsdtPrice();
        if ($rate == 0.0) {
            throw new \DivisionByZeroError('TRX price is zero, cannot convert.');
        }

        return round($usdtAmount / $rate, 6);
    }

    public static function randomAmount(int $base = 50, float $spread = 10): float
    {
        $raw = mt_rand(0, $spread * 100) / 100 + $base;
        return floor($raw * 100) / 100; // 向下截断保留 2 位
    }

    public static function generateOrderId(): string
    {
        $prefix = Carbon::now()->format('YmdHis'); // 时间戳14位
        $suffix = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . $suffix;
    }

    public static function formatAmount(float|string $amount): string
    {
        $formatted = number_format((float)$amount, 2, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    public static function getRechargeAddress(): string
    {
        return \Hyperf\Support\env('RECHARGE_ADDRESS');
    }


    public static function newButton(string $secret, $key, $params, $route, $routeData = []): array
    {
        return [
            'text' => trans("buttons.$key", $params),
            'callback_data' => self::newCallbackData($secret, $route, $routeData),
        ];
    }

    public static function newCallbackData($secret, $route = '', $params = [], int $ttl = 0): string
    {
        if (!$route) $route = 'do_nothing';
        if (!empty($params)) {
            $queries = [];
            foreach ($params as $key => $value) {
                $queries[] = "$key=$value";
            }
            $route = '/' . trim($route, '/') . '?' . implode("&", $queries);
        }
        $hashKey = $route . $secret;
        Logger::debug("hash key => $hashKey");
        $hash = md5($hashKey);
        $hash = "callback_query:$hash";
        $cache = make(Cache::class);
        $cache->set($hash, $route, $ttl);
        Logger::debug("new callbackdata: $hash => $route");
        return $hash;
    }

}
