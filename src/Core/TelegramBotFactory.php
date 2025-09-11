<?php

namespace William\HyperfExtTelegram\Core;

use William\HyperfExtTelegram\Helper\GuzzleClient;
use Hyperf\Guzzle\ClientFactory;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

class TelegramBotFactory
{
    /**
     * @throws TelegramSDKException
     */
    public static function create(ClientFactory $clientFactory, string $token, array $config = [], ?string $endpoint = "https://api.telegram.org"): Api
    {
        return (new TelegramApi($clientFactory, $token, $config, $endpoint))->getTelegram();
    }

    /**
     * @throws TelegramSDKException
     */
    public function get(array $config = []): Api
    {
        return new Api(
            \Hyperf\Support\env('TELEGRAM_BOT_TOKEN'),
            false,
            new GuzzleHttpClient(GuzzleClient::coroutineClient($config)),
            \Hyperf\Support\env('TELEGRAM_ENDPOINT', 'https://api.telegram.org') . '/bot');
    }
}
