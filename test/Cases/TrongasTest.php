<?php

namespace HyperfTest\Cases;

use Hyperf\Testing\TestCase;
use William\HyperfExtTron\Apis\CatFee;
use William\HyperfExtTron\Apis\Trongas;
use William\HyperfExtTron\Tron\Energy\EnergyApiFactory;

class TrongasTest extends TestCase
{
    public function testApi()
    {
        /** @var EnergyApiFactory $factory */
        $factory = $this->container->get(EnergyApiFactory::class);
        /** @var Trongas $api */
        $api = $factory->get(Trongas::class);
        $name = $api->name();
        $this->assertEquals(Trongas::API_NAME, $name);
        $balance = $api->getBalance();
        var_dump($balance);
    }
}