<?php

namespace William\HyperfExtTelegram\Clone;

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\BotManager;
use William\HyperfExtTelegram\Core\Instance;
use William\HyperfExtTelegram\Core\MessageBuilder;
use William\HyperfExtTelegram\Core\RuntimeError;
use William\HyperfExtTelegram\Events;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramBot;
use function Hyperf\Support\make;

class Cloner implements CloneInterface
{

    public function __construct()
    {

    }

    public function start(Instance $instance, int $chatId): void
    {
        $msg = MessageBuilder::newMessage($chatId, $instance->getBotID());
        $msg->text("Welcome to clone this bot, please input token:");
        $msg->send($instance->telegram);
        $instance->startState($chatId, "clone");
    }

    protected function defaultSettings(): array
    {
        return [];
    }

    public function handleTokenInput(Instance $instance, Update $update, string $token): TelegramBot
    {
        Logger::debug("检查token是否已存在:$token");
        if (TelegramBot::where('token', $token)->exists()) {
            throw new RuntimeError("Token already exists");
        }

        $user = $instance->getCurrentUser();

        $tel = new TelegramBot();
        $tel->token = $token;
        try {
            Logger::debug("通过getMe检查token是否有效:$token");
            $api = new Api($token);
            $me = $api->getMe();
            $tel->username = $me->username;
            $tel->nickname = $me->first . ' ' . $me->last;
            $tel->language = $me->languageCode;
        } catch (\Exception $e) {
            throw new RuntimeError("Invalid token");
        }
        Logger::debug("保存机器人:$token");
        $tel->telegram_user_id = $user->id;
        $tel->settings = $this->defaultSettings();
        $tel->save();

        Logger::debug("发送启动机器人事件：$token");
        /** @var BotManager $bm */
        $bm = make(BotManager::class);
        $bm->dispatch($token, Events::START);
        $instance->endState($update);
        return $tel;
    }

}