<?php

namespace William\HyperfExtTelegram\Core;


use William\HyperfExtTelegram\Trait\ReplyTrait;
use William\HyperfExtTelegram\Trait\StateTrait;
use William\HyperfExtTelegram\Trait\UserTrait;
use Telegram\Bot\Objects\Update;

abstract class AbstractQueryCallback implements QueryCallbackInterface
{
    use ReplyTrait, StateTrait, UserTrait;

    protected Instance $telegramInstance;
    protected Update $telegramUpdate;

    function handle(Instance $instance, Update $update): void
    {
        $this->telegramInstance = $instance;
        $this->telegramUpdate = $update;
        $this->_handle();
        $this->telegramInstance->answer($this->telegramUpdate);
    }

    protected function getMessageId(): int
    {
        return $this->telegramUpdate->getCallbackQuery()->getMessage()->getMessageId();
    }

    protected function getQueryParam($key)
    {
        return $this->telegramInstance->getQueryParam($key);
    }


    abstract protected function _handle();
}