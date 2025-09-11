<?php

namespace William\HyperfExtTelegram\Core;


use Telegram\Bot\Objects\Update;

interface CommandInterface
{
    function handle(Instance $instance, Update $update): void;
}