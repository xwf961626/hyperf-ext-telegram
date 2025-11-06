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
    public string $name = 'telegram-process';

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
            $startupFile = "/tmp/startup-telegram.done";
            Logger::debug('当前环境：' . env('APP_ENV'));
            $this->botManager->init(1);
            $this->botManager->start();
            Logger::debug("BotManager启动成功!");
            file_put_contents($startupFile, date('c'));
        } catch (\Throwable $e) {
            Logger::error('启动失败 ' . $e->getMessage() . $e->getTraceAsString());
        }
        $this->listenEvents();
    }

    private function listenEvents()
    {
        while (true) {
            $cmd = $this->redis->get('robot_command');
            if ($cmd) {
                Logger::info("收到机器人命令: $cmd");
                $cmd = json_decode($cmd, true);
                if ($cmd['botId'] && $cmd['command']) {
                    $bot = TelegramBot::find($cmd['botId']);
                    if ($bot) {
                        if ($cmd['command'] === 'stop') {
                            Logger::debug("[botManger]关闭机器人...");
                            $this->botManager->stopBot($bot);
                        }
                        if ($cmd['command'] === 'start') {
                            Logger::debug("[botManger]启动机器人...");
                            $this->botManager->startBot($bot);
                        }
                        if ($cmd['command'] === 'add') {
                            Logger::debug("[botManger]开始添加机器人...");
                            $this->botManager->startBot($bot);
                        }
                        if ($cmd['command'] == 'updateToken') {
                            Logger::debug("[botManger]更新机器人token");
							$token = $cmd['token'];
							$arr1 = explode(':', $token);
							$arr2 = explode(':', $bot->token);
							if($arr1[0] == $arr2[0]) {
								$bot->token = $cmd['token'];
								$bot->save();
							}
                            Logger::debug("关闭旧机器人");
                            $this->botManager->stopBot($bot);
                            $bot->token = $cmd['token'];
                            $bot->save();
                            Logger::debug("[botManger]更新token");
                            Logger::debug("[botManger]启动新机器人");
                            $this->botManager->startBot($bot);
                        }
                        if ($cmd['command'] == 'restart') {
                            Logger::debug("[botManger]重启机器人...");
                            $this->botManager->stopBot($bot);
                            $this->botManager->startBot($bot);
                        }
                        if ($cmd['command'] == 'delete') {
                            Logger::debug("[botManger]删除机器人...");
                            $this->botManager->stopBot($bot);
                            $bot->delete();
                        }
                    }
                    $this->redis->del('robot_command'); // 处理完成后删除
                }
            }
            sleep(1);
        }
    }
}