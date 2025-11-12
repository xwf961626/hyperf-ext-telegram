<?php

namespace William\HyperfExtTelegram\Core;

use Telegram\Bot\Objects\Update;

interface UpdateFilterInterface
{
    public function filter(Instance $instance, Update $update): bool;
}