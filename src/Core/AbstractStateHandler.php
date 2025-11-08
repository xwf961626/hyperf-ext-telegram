<?php

namespace William\HyperfExtTelegram\Core;

use William\HyperfExtTelegram\Trait\ReplyTrait;
use William\HyperfExtTelegram\Trait\StateTrait;
use Telegram\Bot\Objects\Update;
use function Hyperf\Config\config;

abstract class AbstractStateHandler implements StateHandlerInterface
{
    use ReplyTrait, StateTrait;

    protected Instance $telegramInstance;
    protected StateBus $state;
    protected Update $telegramUpdate;
    protected ?string $text;

    public function handle(Instance $instance, Update $update, StateBus $state, ?string $text): void
    {
        $this->telegramInstance = $instance;
        $this->state = $state;
        $this->text = $text;
        $this->telegramUpdate = $update;
        if(config('telegram.state_delete_pre_message'))
        $this->deletePreMessage();
        $this->_handle();
        if(config('telegram.state_delete_current_message'))
        $this->deleteCurrentMessage();
    }

    protected function deletePreMessage()
    {
        $messageID = $this->telegramUpdate->getMessage()->getMessageId() - 1;
        $this->telegramInstance->delMessage($this->telegramUpdate, $messageID);
    }

    protected function deleteCurrentMessage()
    {
        $messageID = $this->telegramUpdate->getMessage()->getMessageId();
        $this->telegramInstance->delMessage($this->telegramUpdate, $messageID);
    }

    abstract protected function _handle();
}