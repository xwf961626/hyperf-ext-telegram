<?php
declare(strict_types=1);

namespace William\HyperfExtTelegram\Process;

use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Annotation\Process;
use Psr\Container\ContainerInterface;
use Swoole\Table;
use William\HyperfExtTelegram\Core\BotManager;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramBot;
use function Hyperf\Support\env;


class TelegramProcess extends AbstractProcess
{
    protected BotManager $botManager;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->botManager = $container->get(BotManager::class);
    }

    public function handle(): void
    {
        $table = $this->container->get(Table::class);
        try {
            Logger::debug('当前环境：' . env('APP_ENV'));
            $this->botManager->start();
        } catch (\Exception $e) {
            Logger::error('启动失败 ' . $e->getMessage() . $e->getTraceAsString());
//            sleep(3);
//            $this->handle();
        }
        while (true) {
            // 检查控制器发来的命令
            $cmd = $table->get('robot_command');
            $c = $cmd['command'] ?? null;
            $botId = $cmd['botId'] ?? null;
            if($botId && $c) {
                $table->set('robot_command', ['command' => '', 'botId' => '']);
                $bot = TelegramBot::find($botId);
                if($bot) {
                    if ($cmd === 'stop') {
                        $this->botManager->stopBot($bot);
                    }
                    if($cmd === 'start') {
                        $this->botManager->startBot($bot);
                    }
                    if($cmd === 'add') {
                        $this->botManager->startBot($bot);
                    }
                }
            }


            sleep(1); // 避免空循环占用 CPU
        }
    }
}