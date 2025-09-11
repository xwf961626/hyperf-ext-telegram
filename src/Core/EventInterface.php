<?php

namespace William\HyperfExtTelegram\Core;

interface EventInterface
{
    public function handle(BotManager $botManager, mixed $event): void;
}