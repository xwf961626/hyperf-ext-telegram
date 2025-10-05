<?php

namespace William\HyperfExtTelegram\Service;

use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;

class AmountPoolService
{
    protected Redis $redis;

    public function __construct(ContainerInterface $container)
    {
        $this->redis = $container->get(RedisFactory::class)->get('default');
    }

    /**
     * 生成带随机小数的金额
     *
     * @param float|int $baseAmount  基础金额，如 100
     * @param int       $n           小数位数
     * @param int       $expireMin   不重复的时间(分钟)
     * @param int       $maxRetry    最大重试次数
     * @return string
     * @throws \RuntimeException
     */
    public function getUniqueAmount(float $baseAmount, int $n = 4, int $expireMin = 5, int $maxRetry = 10): string
    {
        $keyPrefix = "amount_pool:" . $baseAmount . ":" . $n;
        $expireSec = $expireMin * 60;

        for ($i = 0; $i < $maxRetry; $i++) {
            // 生成随机小数部分
            $rand = random_int(0, pow(10, $n) - 1);
            $suffix = str_pad($rand, $n, '0', STR_PAD_LEFT);

            $uniqueKey = "{$keyPrefix}:{$suffix}";

            // NX = 不存在时才设置，EX = 过期时间
            $success = $this->redis->setex($uniqueKey, $expireSec, 1);

            if ($success) {
                // 返回最终金额（保证 n 位小数）
                return number_format($baseAmount, 0, '.', '') . '.' . $suffix;
            }
        }

        throw new \RuntimeException("无法在{$maxRetry}次尝试中生成唯一金额");
    }
}
