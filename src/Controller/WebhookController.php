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


use Hyperf\HttpServer\Router\Router;
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\Instance;
use William\HyperfExtTelegram\Model\TelegramBot;

class WebhookController extends BaseController
{
    public static function addRoutes()
    {
        Router::addRoute(['GET', 'POST'], '/telegram/webhook/{botId}', [self::class, 'handleWebhook']);
    }

    public function handleWebhook($botId)
    {
        $bot = TelegramBot::find($botId);
        if (!$bot) {
            return $this->error('Unauthorized', 401);
        }
        try {
            $updates = $this->request->all();
            $instance = new Instance($bot->token);
            $instance->handleUpdate(Update::make($updates));
            return $this->success(['status' => 'ok']);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
