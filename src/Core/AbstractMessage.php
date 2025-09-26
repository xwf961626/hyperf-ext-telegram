<?php

namespace William\HyperfExtTelegram\Core;

use William\HyperfExtTelegram\Helper\Logger;
use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use function Hyperf\Translation\trans;


abstract class AbstractMessage implements ReplyMessageInterface
{
    protected string $cacheKey = 'default';
    protected Redis $redis;
    protected int $messageId = 0;

    public function __construct(protected Api $telegram, protected int $chatId)
    {
        $this->redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('default');
    }

    protected function newMessage($msgType = 'Message'): MessageBuilder
    {
        $builder = MessageBuilder::newMessage($this->chatId);
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

    public function newCallbackData($route = '', $params = [])
    {
        if (!$route) $route = 'do_nothing';
        if (!empty($params)) {
            $queries = [];
            foreach ($params as $key => $value) {
                $queries[] = "$key=$value";
            }
            $route = '/' . trim($route, '/') . '?' . implode("&", $queries);
        }
        return $route;
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