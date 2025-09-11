<?php

namespace William\HyperfExtTelegram\Core;


use Telegram\Bot\Objects\Update;

interface QueryCallbackInterface
{
    function handle(Instance $instance, Update $update): void;
}