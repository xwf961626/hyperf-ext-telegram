<?php

namespace William\HyperfExtTelegram\Component\Language;

use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\AbstractCommand;
use William\HyperfExtTelegram\Core\Annotation\Command;
use William\HyperfExtTelegram\Core\Instance;

class LanguageCommand extends AbstractCommand
{

    /**
     * @throws TelegramSDKException
     */
    function handle(Instance $instance, Update $update): void
    {
        $instance->reply(new LanguageReply(
            $instance->telegram,
            $instance->getChatId($update),
        ));
    }
}