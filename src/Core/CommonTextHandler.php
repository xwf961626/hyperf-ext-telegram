<?php

namespace William\HyperfExtTelegram\Core;

use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Helper\Logger;

class CommonTextHandler
{
    public function __construct(protected Instance $instance, protected Update $update)
    {
    }

    public function handle(string $message)
    {
        Logger::debug("Input text message: {$message}");
    }
}