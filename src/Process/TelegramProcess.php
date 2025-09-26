<?php
declare(strict_types=1);

namespace William\HyperfExtTelegram\Process;

use Hyperf\Process\AbstractProcess;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;
use William\HyperfExtTelegram\Core\BotManager;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramBot;
use function Hyperf\Support\env;


class TelegramProcess extends AbstractProcess
{
    protected BotManager $botManager;
    protected Redis $redis;
    public function __construct(ContainerInterface $container, RedisFactory $redisFactory)
    {
        parent::__construct($container);
        $this->botManager = $container->get(BotManager::class);
        $this->redis = $redisFactory->get('default');
    }

    public function handle(): void
    {
        try {
            Logger::debug('当前环境：' . env('APP_ENV'));
            $this->botManager->init(1);
            $this->botManager->start();
        } catch (\Exception $e) {
            Logger::error('启动失败 ' . $e->getMessage() . $e->getTraceAsString());
            sleep(3);
            $this->handle();
        }
        while (true) {
            $cmd = $this->redis->get('robot_command');
            if ($cmd) {
                Logger::info("收到机器人命令: $cmd");
                $cmd = json_decode($cmd, true);
                if ($cmd['botId'] && $cmd['command']) {
                    $bot = TelegramBot::find($cmd['botId']);
                    if($bot) {
                        if ($cmd['command'] === 'stop') {
                            $this->botManager->stopBot($bot);
                        }
                        if($cmd['command'] === 'start') {
                            $this->botManager->startBot($bot);
                        }
                        if($cmd['command'] === 'add') {
                            $this->botManager->startBot($bot);
                        }
                    }
                    $this->redis->del('robot_command'); // 处理完成后删除
                }
            }
            sleep(1);
        }

    }
}