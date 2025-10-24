<?php

namespace William\HyperfExtTelegram\Clone;

use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\Instance;

interface CloneInterface
{
    public function start(Instance $instance, int $chatId);

    public function handleTokenInput(Instance $instance, Update $update, string $token);
}