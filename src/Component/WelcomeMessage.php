<?php

namespace William\HyperfExtTelegram\Component;

use Telegram\Bot\Api;
use William\HyperfExtTelegram\Core\AbstractMessage;
use William\HyperfExtTelegram\Lang;

class WelcomeMessage extends AbstractMessage
{
    public function __construct(Api               $telegram, int $chatId,
                                protected array   $keyboards,
                                protected ?string $username = null)
    {
        parent::__construct($telegram, $chatId);
    }

    public function reply(): void
    {
        $this->newMessage()
            ->photo('welcome.jpg')
            ->transMessage(Lang::MESSAGE_WELCOME, ['bot_username' => $this->username ?? ""])
            ->replyMarkup($this->keyboards)
            ->send($this->telegram);
    }
}