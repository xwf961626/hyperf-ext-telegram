<?php

namespace William\HyperfExtTelegram\Core;


use William\HyperfExtTelegram\Trait\ReplyTrait;
use William\HyperfExtTelegram\Trait\UserTrait;
use Telegram\Bot\Objects\Update;

abstract class AbstractMenu implements QueryCallbackInterface
{
    use ReplyTrait, UserTrait;

    protected Instance $telegramInstance;
    protected Update $telegramUpdate;
    public function handle(Instance $instance, Update $update): void
    {
        $this->telegramInstance = $instance;
        $this->telegramUpdate = $update;

        $messageID = $update->getMessage()->getMessageId();
        $instance->delMessage($update, $messageID);
        $this->_handle();
    }

    abstract protected function _handle(): void;
}