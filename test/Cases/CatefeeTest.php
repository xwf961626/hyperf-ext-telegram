<?php

namespace HyperfTest\Cases;

use Hyperf\Testing\TestCase;
use William\HyperfExtTron\Apis\CatFee;
use William\HyperfExtTron\Tron\Energy\EnergyApiFactory;

class CatefeeTest extends TestCase
{
    public function testApi()
    {
        /** @var EnergyApiFactory $factory */
        $factory = $this->container->get(EnergyApiFactory::class);
        /** @var CatFee $api */
        $api = $factory->get(CatFee::class);
        $name = $api->name();
        $this->assertEquals(CatFee::API_NAME, $name);
//        $api->send('THH5zsXVQ8dSy7FNg1putmh6cR4Eeu5kix', 65000, '1h', 1);
        $api->getBalance();
    }
}