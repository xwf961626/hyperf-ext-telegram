<?php

namespace William\HyperfExtTelegram\Core;

use Hyperf\Cache\Cache;
use William\HyperfExtTelegram\Helper\Logger;
use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use function Hyperf\Support\make;
use function Hyperf\Translation\trans;


abstract class AbstractMessage implements ReplyMessageInterface
{
    protected string $cacheKey = 'default';
    protected Redis $redis;

    public function __construct(protected Api $telegram, protected int $chatId)
    {
        $this->redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('default');
    }

    protected function newMessage($msgType = 'Message', $botId = ''): MessageBuilder
    {
        $builder = MessageBuilder::newMessage($this->chatId, $botId);
        $builder->messageType($msgType);
        return $builder;
    }

    /**
     * @throws TelegramSDKException
     */
    protected function sendMessage(MessageBuilder $builder): void
    {
        $builder->send($this->telegram);
    }

    /**
     * @throws TelegramSDKException
     */
    protected function editMessage(MessageBuilder $builder): void
    {
        $builder->edit($this->telegram, $this->messageId);
    }

    protected function send(callable $getParams)
    {
        $key = 'message:' . $this->cacheKey . ':' . $this->chatId;
        if (!$this->redis->exists($key)) {
            Logger::info("使用缓存 Message#$key");
            $params = $getParams();
            $this->redis->set($key, json_encode($params));
            $this->redis->expire($key, 30);
        } else {
            $params = json_decode($this->redis->get($key), true);
        }
        $this->telegram->sendMessage($params);
    }

    protected function trans(string $key, array $params = []): string
    {
        return trans($key, $params);
    }

    protected function transMessage(string $key, array $params = []): string
    {
        return trans("message.$key", $params);
    }

    public function newCallbackData($route = '', $params = [], int $ttl = 0)
    {
        if (!$route) $route = 'do_nothing';
        if (!empty($params)) {
            $queries = [];
            foreach ($params as $key => $value) {
                $queries[] = "$key=$value";
            }
            $route = '/' . trim($route, '/') . '?' . implode("&", $queries);
        }
        $hashKey = $route . $this->telegram->getAccessToken().$this->chatId;
        Logger::debug("hash key => $hashKey");
        $hash = md5($hashKey);
        $hash = "callback_query:$hash";
        $cache = make(Cache::class);
        $cache->set($hash, $route, $ttl);
        Logger::debug("new callbackdata: $hash => $route");
        return $hash;
    }

    public function newButton($key, $params = [], $route = '', $routeData = []): array
    {
        return [
            'text' => trans("buttons.$key", $params),
            'callback_data' => $this->newCallbackData($route, $routeData),
        ];
    }

    public function newReturnButton($to): array
    {
        return [
            'text' => trans('buttons.return'),
            'callback_data' => $this->newCallbackData('return', ['to' => $to]),
        ];
    }

}