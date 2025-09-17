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
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\Instance;
use William\HyperfExtTelegram\Model\TelegramBot;

class WebhookController extends BaseController
{
    private Cache $cache;

    public function __construct(Cache $cache)
    {
        parent::__construct();
        $this->cache = $cache;
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
        try {
            $updates = $this->request->all();
            $instance = new Instance($bot);
            $instance->handleUpdate(Update::make($updates));
            return $this->success(['status' => 'ok']);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
