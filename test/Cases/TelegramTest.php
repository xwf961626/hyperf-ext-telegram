<?php

namespace HyperfTest\Cases;

use Hyperf\Testing\TestCase;
use William\HyperfExtTelegram\Core\BotManager;
use function Hyperf\Support\make;

class TelegramTest extends TestCase
{
    public function testApi()
    {
        /** @var BotManager $mng */
        $mng = make(BotManager::class);
        $mng->start();
    }
}