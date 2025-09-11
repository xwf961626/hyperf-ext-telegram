<?php

namespace HyperfTest\Cases;

use Hyperf\Testing\TestCase;
use William\HyperfExtTron\Tron\TronService;
use function Hyperf\Support\make;

class TronServiceTest extends TestCase
{
    public function testGetApiList()
    {
        $service = make(TronService::class);
        $list = $service->getApiList();
        var_dump($list);
    }
}