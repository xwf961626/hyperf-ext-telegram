<?php

namespace William\HyperfExtTelegram\Core;

use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;

class StateBus
{
    protected Redis $redis;
    const STATE_KEY_PREFIX = "states:";
    private string $key;

    public function __construct(protected $chatId, protected string $name = "default", protected int $ttl = 0)
    {
        $this->redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('default');
        $this->key = self::STATE_KEY_PREFIX . $chatId;
        if (!$this->redis->hGet($this->key, "name")) {
            $this->redis->hSet($this->key, "name", $this->name);
            if ($ttl > 0) {
                $this->redis->expire($this->key, $this->ttl);
            }
        }
    }

    public function name(): string
    {
        return $this->name;
    }


    public function end()
    {
        $this->redis->del($this->key);
    }

    public function add($hashKey, $value)
    {
        $this->redis->hSet($this->key, $hashKey, $value);
    }

    public function get($hashKey, $defaultValue = null)
    {
        $value = $this->redis->hGet($this->key, $hashKey);
        return $value ?? $defaultValue;
    }
}