<?php

namespace William\HyperfExtTelegram\Core;

use William\HyperfExtTelegram\Trait\ReplyTrait;
use Telegram\Bot\Objects\Update;

abstract class AbstractError implements ErrorInterface
{
    use ReplyTrait;

    protected Instance $telegramInstance;
    protected Update $telegramUpdate;
    protected array $extra = [];

    public function notify(Instance $instance, Update $update, array $extra = []): void
    {
        $this->telegramInstance = $instance;
        $this->telegramUpdate = $update;
        $this->extra = $extra;
        $this->handle();
    }

    abstract protected function handle();
}