<?php

namespace William\HyperfExtTelegram\Core;

use Telegram\Bot\Objects\Update;

interface StateHandlerInterface
{
    public function handle(Instance $instance, Update $update, StateBus $state, string $text);
}