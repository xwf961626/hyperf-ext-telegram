### 安装
```
composer require wenfeng/hyperf-ext-telegram

// 发布配置文件
php bin/hyperf vendor:publish wenfeng/hyperf-ext-telegram

// 数据库迁移
php bin/hyperf migrate
```
### 使用
#### 机器人command
使用注解
``` William\HyperfExtTelegram\Core\Annotation\Command ```
````php
<?php

namespace App\Bot\Command;

use App\Bot\Reply;
use App\FlushEnergyMatrix\MatrixService;
use App\Helper\Logger;
use App\Model\User;
use Hyperf\Contract\TranslatorInterface;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\AbstractCommand;
use William\HyperfExtTelegram\Core\Annotation\Command;
use William\HyperfExtTelegram\Core\Instance;
use function Hyperf\Support\make;

#[Command(command: '/start')]
class StartCommand extends AbstractCommand
{
    protected MatrixService $matrix;

    public function __construct(TranslatorInterface $translator)
    {
        parent::__construct($translator);
        $this->matrix = make(MatrixService::class);
    }

    /**
     * @throws TelegramSDKException
     */
    public function handle(Instance $instance, Update $update): void
    {
        $instance->reply(new Reply\WelcomeMessage(
            $instance->telegram,
            $instance->getChatId($update),
            $this->matrix->getRandomAddressList($instance->getBotID()),
        ));
    }
}
