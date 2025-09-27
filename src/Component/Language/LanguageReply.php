<?php

namespace William\HyperfExtTelegram\Component\Language;

use William\HyperfExtTelegram\Core\AbstractMessage;
use William\HyperfExtTelegram\Lang;

class LanguageReply extends AbstractMessage
{

    public function reply(): void
    {
        $this->newMessage()
            ->transMessage(Lang::MESSAGE_CHOOSE_LANGUAGE)
            ->addRow()
            ->addButton(Lang::BUTTON_CHINESE, [], $this->newCallbackData('switch_language', ['lang' => 'zh_CN']))
            ->addButton(Lang::BUTTON_ENGLISH, [], $this->newCallbackData('switch_language', ['lang' => 'en']))
            ->send($this->telegram);
    }
}