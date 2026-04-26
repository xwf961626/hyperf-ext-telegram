<?php

namespace William\HyperfExtTelegram\Job;

use Hyperf\AsyncQueue\Job;
use Swoole\Coroutine;
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\Instance;
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

    public function handle()
    {
        try {
            // 在这里处理更新，可以调用 handleUpdate
            Logger::info("telegram update 回调处理: ".json_encode($this->updateArray));
            $instance = new Instance(TelegramBot::find($this->botId));
            $update = Update::make($this->updateArray);
            $instance->handleUpdate($update);
        } catch (\Throwable $e) {
            Coroutine::sleep(0.1); // 可选：避免阻塞
            // 异常记录
            Logger::error("处理 TelegramUpdateJob 异常: " . $e->getMessage());
        }
    }
}
