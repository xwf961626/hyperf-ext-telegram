<?php

namespace William\HyperfExtTelegram\Helper;

use GuzzleHttp\HandlerStack;
use Hyperf\Context\ApplicationContext;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Guzzle\CoroutineHandler;

class GuzzleClient
{
    public static function coroutineClient($option = [])
    {
        $config = array_merge([
            'handler' => HandlerStack::create(new CoroutineHandler()),
            'timeout' => 5,
            'swoole' => [
                'timeout' => 10,
                'socket_buffer_size' => 1024 * 1024 * 2,
            ],
            'debug' => true,
        ], $option);
        return ApplicationContext::getContainer()->get(ClientFactory::class)->create($config);
    }
}