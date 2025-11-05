<?php

namespace William\HyperfExtTelegram\Core;

use Telegram\Bot\Objects\Update;

interface Listener
{
    public function handle(Instance $instance,Update $update);
}