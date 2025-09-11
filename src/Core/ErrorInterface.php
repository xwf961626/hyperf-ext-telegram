<?php

namespace William\HyperfExtTelegram\Core;

use Telegram\Bot\Objects\Update;

interface ErrorInterface
{
    public function notify(Instance $instance, Update $update, array $extra = []): void;
}