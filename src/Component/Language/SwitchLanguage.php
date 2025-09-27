<?php

namespace William\HyperfExtTelegram\Component\Language;

use Telegram\Bot\Exceptions\TelegramSDKException;
use William\HyperfExtTelegram\Component\WelcomeMessage;
use William\HyperfExtTelegram\Core\AbstractQueryCallback;
use William\HyperfExtTelegram\Core\Annotation\QueryCallback;
use William\HyperfExtTelegram\Core\LangContext;

#[QueryCallback(path: '/switch_language')]
class SwitchLanguage extends AbstractQueryCallback
{
    /**
     * @throws TelegramSDKException
     * @throws \RedisException
     */
    function _handle(): void
    {
        $this->telegramInstance->changeLanguage($this->telegramUpdate);

        $this->reply(WelcomeMessage::class,
            $this->telegramInstance->getKeyboards()[LangContext::get()],
            $this->telegramInstance->getBotCache()['username']
        );
    }
}