<?php

namespace William\HyperfExtTelegram\Component;

use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\AbstractCommand;
use William\HyperfExtTelegram\Core\Annotation\Command;
use William\HyperfExtTelegram\Core\Instance;
use William\HyperfExtTelegram\Core\LangContext;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramUser;

#[Command(command: '/start')]
class StartCommand extends AbstractCommand
{
    /**
     * @throws TelegramSDKException
     */
    public function handle(Instance $instance, Update $update): void
    {
        $chatId = $instance->getChatId($update); // 消息来自群里面的机器人时，这个chatId变成群ID了，
        $botToken = $instance->getAccessToken();
        $arr = explode(":", $botToken);
        $botId = $arr[0];
        $userInfo = [
            'bot_id' => $botId,
            'user_id' => $chatId,
            'username' => $instance->getUsername($update),
            'nickname' => $instance->getNickname($update),
        ];
        $user = TelegramUser::updateOrCreate([
            'bot_id' => $botId,
            'user_id' => $chatId,
        ], $userInfo);
        Logger::debug('同步用户信息成功：' . $user->id.' => '.json_encode($userInfo));


        $instance->reply(new WelcomeMessage(
            $instance->telegram,
            $instance->getChatId($update),
            $instance->getKeyboards()[LangContext::get()],
            $instance->getBotCache()['username'],
        ));
    }
}