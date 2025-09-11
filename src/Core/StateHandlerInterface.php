<?php

namespace William\HyperfExtTelegram\Core;

use Telegram\Bot\Objects\Update;

interface StateHandlerInterface
{
    public function handle(Instance $instance, Update $update, StateEntity $state, string $text);
}