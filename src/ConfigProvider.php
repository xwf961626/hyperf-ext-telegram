<?php

namespace William\HyperfExtTelegram;


class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                \Psr\SimpleCache\CacheInterface::class => \Hyperf\Cache\Cache::class,
            ],
            'commands' => [
            ],
            'listeners' => [
            ],
            // 合并到  config/autoload/annotations.php 文件
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'tron',
                    'description' => 'tron',
                    'source' => __DIR__ . '/../publish/telegram.php',
                    'destination' => BASE_PATH . '/config/autoload/telegram.php',
                ],
                [
                    'id' => 'migrations',
                    'description' => 'telegram bot migrations',
                    'source' => __DIR__ . '/../migrations/',
                    'destination' => BASE_PATH . '/migrations/',
                ],
            ]
        ];
    }
}