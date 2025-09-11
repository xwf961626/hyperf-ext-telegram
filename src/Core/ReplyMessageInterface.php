<?php

namespace William\HyperfExtTelegram\Core;

use Telegram\Bot\Exceptions\TelegramSDKException;


interface ReplyMessageInterface
{
    /**
     * @throws TelegramSDKException
     */
    public function reply(): void;
}