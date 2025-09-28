<?php

namespace William\HyperfExtTelegram\Component\Language;

use Telegram\Bot\Exceptions\TelegramSDKException;
use William\HyperfExtTelegram\Core\AbstractQueryCallback;
use William\HyperfExtTelegram\Core\LangContext;
use function Hyperf\Config\config;

class SwitchLanguage extends AbstractQueryCallback
{
    /**
     * @throws TelegramSDKException
     * @throws \RedisException
     */
    function _handle(): void
    {
        $this->telegramInstance->changeLanguage($this->telegramUpdate);
        $keyboards = $this->telegramInstance->getKeyboards();

        $this->reply(config('telegram.welcome'),
            $keyboards[LangContext::get()]??[],
            $this->telegramInstance->getBotCache()['username']
        );
    }
}