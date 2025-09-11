<?php
namespace HyperfTest\Cases;

use Hyperf\Testing\TestCase;
use William\HyperfExtTron\Apis\Weidubot;
use William\HyperfExtTron\Tron\Energy\Attributes\EnergyApi;
use William\HyperfExtTron\Tron\Energy\EnergyApiFactory;

/**
 * @internal
 * @coversNothing
 */
class WeiduTest extends TestCase
{
    public function testWeiduapi()
    {
        /** @var EnergyApiFactory $factory */
        $factory = $this->container->get(EnergyApiFactory::class);
        /** @var Weidubot $api */
        $api = $factory->get(Weidubot::class);
        $name = $api->name();
        $this->assertEquals('weidu', $name);
        $result = $api->getBalance();
        var_dump($result);
    }
}