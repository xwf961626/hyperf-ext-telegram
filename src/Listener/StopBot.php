<?php

namespace William\HyperfExtTelegram\Listener;

use William\HyperfExtTelegram\Core\Annotation\Event;
use William\HyperfExtTelegram\Core\BotManager;
use William\HyperfExtTelegram\Core\EventInterface;

#[Event(event: 'stop')]
class StopBot implements EventInterface
{

    public function handle(BotManager $botManager, mixed $event): void
    {
        $botManager->stopBot($event->token);
    }
}