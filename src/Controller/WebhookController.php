<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace William\HyperfExtTelegram\Controller;


use Hyperf\Cache\Cache;
use Hyperf\HttpServer\Router\Router;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\Instance;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramBot;

class WebhookController extends BaseController
{
    private Cache $cache;
    protected Redis $redis;

    public function __construct(Cache $cache, RedisFactory $redisFactory)
    {
        parent::__construct();
        $this->cache = $cache;
        $this->redis = $redisFactory->get('default');
    }

    public static function addRoutes()
    {
        Router::addRoute(['GET', 'POST'], '/telegram/webhook/{botId}/{token}', [self::class, 'handleWebhook']);
    }

    public function handleWebhook($botId, $token)
    {
        $sign = $this->cache->get("webhook_token:$botId");
        if (!$sign || $sign !== $token) return $this->error('Invalid signature', 403);
        $bot = TelegramBot::find($botId);
        if (!$bot) {
            return $this->error('Invalid botId', 401);
        }

        $updates = $this->request->all();

        \Hyperf\Coroutine\go(function () use ($bot, $updates) {
            try {
                $instance = new Instance($bot);
                $update = Update::make($updates);
                $lockKey = "telegram:update_lock:{$update->getChat()->id}";
                Logger::debug("update lock: $lockKey");
                $lockTtl = 300; // 秒，锁有效期（5分钟）
                // 1. Redis锁防止并发重复
                $isFirst = $this->redis->set($lockKey, 1, ['NX', 'EX' => $lockTtl]);
                if (!$isFirst) {
                    Logger::debug("跳过频繁的update id: {$update['update_id']}");
                    return $this->success(['status' => 'ok']);
                }
                $instance->handleUpdate($update);
            } catch (\Exception $e) {
                return $this->error($e->getMessage());
            } finally {
                $this->redis->del($lockKey);
            }
            return $this->success(['status' => 'ok']);
        });

        return $this->success(['status' => 'ok']);

    }
}
