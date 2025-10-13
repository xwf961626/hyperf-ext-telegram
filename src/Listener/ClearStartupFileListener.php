<?php

declare(strict_types=1);

namespace William\HyperfExtTelegram\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeServerStart;
use Hyperf\Framework\Event\BootApplication;
use William\HyperfExtTelegram\Helper\Logger;

class ClearStartupFileListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        Logger::debug("服务器启动之前删除 /tmp/startup-telegram.done");
        $startupFile = '/tmp/startup-telegram.done';
        if (file_exists($startupFile)) {
            unlink($startupFile);
        }
    }
}
