<?php

namespace William\HyperfExtTelegram\Job;

use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\RedisFactory;
use Swoole\Coroutine;
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\ErrorHandlerFactory;
use William\HyperfExtTelegram\Core\Instance;
use William\HyperfExtTelegram\Core\RuntimeError;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramBot;

class TelegramUpdateJob extends Job
{
    public $updateArray;
    public $botId;

    public function __construct($updateArray, $botId)
    {
        $this->updateArray = $updateArray;
        $this->botId = $botId;
    }

    public function handle(): void
    {
        Logger::info("telegram update 回调处理: ".json_encode($this->updateArray));
        $instance = new Instance(TelegramBot::find($this->botId));
        $update = Update::make($this->updateArray);
        try {
            // 在这里处理更新，可以调用 handleUpdate
            $instance->handleUpdate($update);
        } catch (RuntimeError $e) {
            try {
                if ($errorHandler = ApplicationContext::getContainer()->get(ErrorHandlerFactory::class)->get($e->getMessage())) {
                    $handlerClass = get_class($errorHandler);
                    Logger::debug("Error {$e->getMessage()} handled by {$handlerClass}.");
                    $errorHandler->notify($instance, $update, $e->getExtra());
                } else {
                    Logger::error("未定义的错误处理器 {$e->getMessage()}");
                }
            } catch (\Exception $e) {
                Logger::error("pulling 异常 {$e->getMessage()}");
            }
        } catch (\Throwable $e) {
            Logger::info("handleUpdate未知异常:" . $e->getMessage() . $e->getTraceAsString());
        }
    }
}
