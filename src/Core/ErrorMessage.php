<?php

namespace William\HyperfExtTelegram\Core;

use Telegram\Bot\Api;
use William\HyperfExtTelegram\Core\AbstractMessage;
use William\HyperfExtTelegram\Core\MessageDecorator;

/**
 * @var MessageDecorator|null $setInlineKeyboards
 */
class ErrorMessage extends AbstractMessage
{
    public function __construct(Api                         $telegram, int $chatId,
                                protected string            $error,
                                protected array             $params = [],
                                protected ?MessageDecorator $messageDecorator = null,
    )
    {
        parent::__construct($telegram, $chatId);
    }

    public function reply(): void
    {
        $builder = $this->newMessage()
            ->transMessage($this->error, $this->params);
        if ($this->messageDecorator !== null) {
            $this->messageDecorator->decorate($builder);
        }
        $builder->send($this->telegram);

    }
}