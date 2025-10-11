### 安装
```
composer require wenfeng/hyperf-ext-telegram

// 发布配置文件
php bin/hyperf vendor:publish wenfeng/hyperf-ext-telegram

// 数据库迁移
php bin/hyperf migrate
```
### 使用
#### 消息回复写法示例
````php
<?php

namespace App\Bot\Reply;

use App\Constants\BotConstants;
use App\Context\LangContext;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use William\HyperfExtTelegram\Core\AbstractMessage;

class WelcomeMessage extends AbstractMessage
{
    public function __construct(Api               $telegram, int $chatId,
                                protected array   $keyboards,
                                protected ?string $username = null)
    {
        parent::__construct($telegram, $chatId);
    }

    /**
     * @throws TelegramSDKException
     */
    public function reply(): void
    {
        $this->newMessage()
            ->photo('welcome.jpg')
            ->transMessage(BotConstants::MESSAGE_WELCOME, ['bot_username' => $this->username ?? ""])
            ->addRow()->addButton(//按钮...)
            ->replyMarkup($this->keyboards)
            ->send($this->telegram);
    }
}
````
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
````
#### 按钮 Query Callback 注解
````php
<?php

namespace App\Bot\Query;

use App\Bot\Reply\WelcomeMessage;
use App\Helper\Logger;
use Hyperf\Contract\TranslatorInterface;
use Telegram\Bot\Exceptions\TelegramSDKException;
use William\HyperfExtTelegram\Core\AbstractQueryCallback;
use William\HyperfExtTelegram\Core\Annotation\QueryCallback;

#[QueryCallback(path: '/switch_language')]
class SwitchLanguage extends AbstractQueryCallback
{

    public function __construct(TranslatorInterface $translator, protected MatrixService $matrix)
    {
    }

    /**
     * @throws TelegramSDKException
     * @throws \RedisException
     */
    function _handle(): void
    {
        Logger::debug("switching language...");
        $this->telegramInstance->changeLanguage($this->telegramUpdate);
        $this->reply(WelcomeMessage::class);
    }
}
````
#### 底部菜单Menu注解
````php
<?php

namespace App\Bot\Menu;

use App\Bot\Menus;
use App\Bot\Reply\MyInfo;
use App\Helper\Logger;
use App\Service\UserService;
use Telegram\Bot\Exceptions\TelegramSDKException;
use William\HyperfExtTelegram\Core\AbstractMenu;
use William\HyperfExtTelegram\Core\Annotation\Menu;

#[Menu(menu: Menus::MINE)]
class MineMenu extends AbstractMenu
{
    public function __construct(protected UserService $userService)
    {
    }

    /**
     * @throws TelegramSDKException
     */
    function _handle(): void
    {
        Logger::debug("Menu#mine ...");
        $this->reply(MyInfo::class, $this->userService->getUserInfo($this->telegramInstance->getCurrentUser()));
    }
}
````


### 充值服务
````php
use William\HyperfExtTelegram\Service\RechargeService;
````
