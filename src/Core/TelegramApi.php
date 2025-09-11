<?php

namespace William\HyperfExtTelegram\Core;

use William\HyperfExtTelegram\Helper\GuzzleClient;
use Hyperf\Guzzle\ClientFactory;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

class TelegramApi
{
    protected Api $telegram;

    public function __construct(
        protected ClientFactory $clientFactory,
        protected string        $token,
        protected array         $config = [],
        ?string                 $endpoint = "https://api.telegram.org",
    )
    {
        $this->initTelegram($endpoint);
    }

    protected function initTelegram($endpoint): void
    {
        try {
            $this->telegram = new Api($this->token, false, new GuzzleHttpClient(GuzzleClient::coroutineClient($this->config)), $endpoint.'/bot');
        } catch (TelegramSDKException $e) {
            throw new \RuntimeException('Telegram Bot 初始化失败: ' . $e->getMessage());
        }
    }

    public function getTelegram(): Api
    {
        return $this->telegram;
    }

    // 下面添加各种 Telegram 功能封装方法

    /**
     * @throws TelegramSDKException
     */
    public function sendMessage(int $chatId, string $text, array $params = []): \Telegram\Bot\Objects\Message
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $params);

        return $this->telegram->sendMessage($params);
    }
}