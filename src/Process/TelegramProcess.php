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
use function Hyperf\Config\config;
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
            $onBotManagerStartedHandler = config('telegram.on_bot_manager_started');
            if ($onBotManagerStartedHandler) {
                $onBotManagerStartedHandler();
            }
            file_put_contents($startupFile, date('c'));
        } catch (\Throwable $e) {
            Logger::error('启动失败 ' . $e->getMessage() . $e->getTraceAsString());
        }
        \Hyperf\Coroutine\go(function () use ($startupFile) {
            $this->readQueue();
        });
//        \Hyperf\Coroutine\go(function () use ($startupFile) {
//            $this->listenEvents();
//        });
    }

    private function readQueue($streamName = 'robot_command_queue', $groupName = 'robot_group', $consumerName = 'consumer1')
    {
        Logger::debug("开始读取 robot_command_queue 队列");

        // 创建消费者组（只在流不存在时创建）
        try {
            $this->redis->xGroup('CREATE', $streamName, $groupName, '$', true);  // '$' 表示从最新消息开始
        } catch (Exception $e) {
            Logger::debug("消费者组已存在，跳过创建");
        }

        // 无限循环，持续读取消息
        while (true) {
            // 从消费者组中读取消息
            $streams = $this->redis->xReadGroup($groupName, $consumerName, [$streamName => '>'], 0, 1);  // '>' 表示从未处理的消息开始读取

            if ($streams) {
                foreach ($streams as $stream => $messages) {
                    foreach ($messages as $id => $message) {
                        // 输出消息
                        echo "Message ID: $id\n";
                        print_r($message);

                        // 处理命令
                        $this->handleCommand($message);

                        // 确认消息已处理
                        $this->redis->xAck($streamName, $groupName, [$id]);
                    }
                }
            }

            // 如果队列为空，稍作等待再重新检查
            sleep(1);  // 控制循环的频率，避免过度消耗 CPU
        }

        Logger::debug("结束读取 robot_command_queue 队列");
    }



    private function handleCommand(array $cmd)
    {
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
                if ($arr1[0] == $arr2[0]) {
                    $bot->token = $cmd['token'];
                    $bot->save();
                }
                Logger::debug("关闭旧机器人");
                $this->botManager->stopBot($bot);
//                            $bot->token = $cmd['token'];
//                            $bot->save();
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
    }

    private function listenEvents()
    {
        while (true) {
            $cmd = $this->redis->get('robot_command');
            if ($cmd) {
                Logger::info("收到机器人命令: $cmd");
                $cmd = json_decode($cmd, true);
                if ($cmd['botId'] && $cmd['command']) {
                    $this->handleCommand($cmd);
                    $this->redis->del('robot_command'); // 处理完成后删除
                }
            }
            sleep(1);
        }
    }
}