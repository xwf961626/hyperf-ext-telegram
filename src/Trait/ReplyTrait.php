<?php

namespace William\HyperfExtTelegram\Trait;

use William\HyperfExtTelegram\Core\LangContext;
use William\HyperfExtTelegram\Core\MessageDecorator;
use William\HyperfExtTelegram\Core\ErrorMessage;
use William\HyperfExtTelegram\Helper\Logger;
use function Hyperf\Translation\trans;

trait ReplyTrait
{
    protected function reply(string $class, ...$args): void
    {
        $this->telegramInstance->reply(
            new $class(
                $this->telegramInstance->telegram,
                $this->telegramInstance->getChatId($this->telegramUpdate),
                ...$args)
        );
    }

    protected function error(string $type, array $params = [], ?MessageDecorator $decorator = null): void
    {
        $this->reply(ErrorMessage::class, $type, $params, $decorator);
    }

    protected function deleteMessage()
    {
        $this->telegramInstance->delMessage($this->telegramUpdate);
    }

    protected function alert(string $type, array $params = [], $confirm = false): void
    {
        $lang = LangContext::get();
        Logger::debug('show alert => locale:' . $lang);
        $this->telegramInstance->answer($this->telegramUpdate, trans('message.'.$type, $params, $lang), $confirm);
    }

}