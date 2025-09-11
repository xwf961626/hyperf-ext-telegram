<?php

namespace William\HyperfExtTelegram\Example;

use William\HyperfExtTelegram\Core\AbstractMessage;

class ExampleReply extends AbstractMessage
{

    public function reply(): void
    {
        $this->newMessage()->send();
    }
}