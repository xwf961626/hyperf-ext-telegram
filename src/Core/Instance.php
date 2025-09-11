<?php

namespace William\HyperfExtTelegram\Core;

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\Annotation\AnnotationRegistry;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramBot;
use William\HyperfExtTelegram\Model\TelegramUser;
use function Hyperf\Config\config;
use function Hyperf\Support\make;

class Instance
{
    const QUERY_PARAMS_KEY = 'query_params';
    const USER_KEY = 'user';
    protected string $token;
    private Redis $redis;
    /**
     * @var ClientFactory|mixed
     */
    protected ClientFactory $clientFactory;
    public Api $telegram;
    protected array $messages;
    protected array $menuMap;
    protected array $menus;
    protected TranslatorInterface $translator;
    protected int $botID;
    protected $keyboards;
    protected TelegramBot $bot;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->token = $bot->token;
        $arr = explode(':', $bot->token);
        $this->botID = (int)$arr[0];
        $this->redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('default');
        $this->clientFactory = ApplicationContext::getContainer()->get(ClientFactory::class);
        $this->translator = ApplicationContext::getContainer()->get(TranslatorInterface::class);
        $this->init();
    }

    /**
     * @throws TelegramSDKException
     */
    private function init(): void
    {
        $telegram = TelegramBotFactory::create($this->clientFactory, $this->token, [],
            \Hyperf\Support\env('TG_ENDPOINT', 'https://api.telegram.org'));
        $telegram->setMyCommands([
            'commands' => [
                [
                    'command' => 'start',
                    'description' => '开始使用'
                ],
                [
                    'command' => 'language',
                    'description' => '切换语言'
                ],
                [
                    'command' => 'help',
                    'description' => '获取帮助'
                ]
            ]
        ]);
        $this->telegram = $telegram;
    }

    public function webhook(bool $condition = true): void
    {
        if ($condition) {
            $url = \Hyperf\Support\env('BOT_WEBHOOK_BASE') . $this->botID;
            Logger::debug("添加webhook:" . $url);
            $this->telegram->setWebhook([
                'url' => $url,
            ]);
        }
    }

    public function polling(bool $condition): void
    {
        $offset = 0;
        $this->telegram->deleteWebhook();
        while ($condition) {
            try {
                $updates = $this->telegram->getUpdates([
                    'offset' => $offset,
                    'limit' => 100,
                    'timeout' => 3,
                    'connect_timeout' => 5,
                ]);
                foreach ($updates as $update) {
                    $offset = $update['update_id'] + 1;
                    $this->handleUpdate($update);
                }
            } catch (RuntimeError $e) {
                try {
                    if ($errorHandler = ApplicationContext::getContainer()->get(ErrorHandlerFactory::class)->get($e->getMessage())) {
                        $errorHandler->notify($this, $update, $e->getExtra());
                    } else {
                        Logger::error("未定义的错误处理器 {$e->getMessage()}");
                    }
                } catch (\Exception $e) {
                    Logger::error("polling 异常 {$e->getMessage()}");
                }

            } catch (\Exception $e) {
                Logger::info("未知异常:" . $e->getMessage() . $e->getTraceAsString());
            }
        }
    }

    public function start(bool $condition = true, string $method = 'polling'): void
    {
        $me = $this->telegram->getMe();
        Logger::info("bot getMe => " . json_encode($me));
        TelegramBot::where(['token' => $this->token])->update(['username' => $me->username, 'nickname' => $me->firstName]);
//        $this->polling($condition);
        call_user_func([$this, $method], $condition);
    }

    public function getBotCache(): array
    {
        $key = 'bot:' . $this->token;
        if (!$this->redis->exists($key)) {
            $botCache = TelegramBot::where('token', $this->token)->first();
            $this->redis->set($key, json_encode($botCache));
            return $botCache->toArray();
        }
        return json_decode($this->redis->get($key), true);
    }

    private function getUserLanguage($chatId)
    {
        $locale = $this->redis->get('locale:' . $this->botID . ':' . $chatId);
        if (!$locale) {
            $botCache = $this->getBotCache();
            $locale = $botCache['language'] ?: 'zh_CN';
        }
        return $locale;
    }

    /**
     * @param Update $update
     * @return void
     * @throws RuntimeError
     */
    public function handleUpdate(Update $update): void
    {
        Logger::info("Telegram update => " . json_encode($update));
        $chat = $update->getChat();
        $chatId = $chat->id;
        Logger::info("chat id => {$chatId}, chat title => {$chat->title}");
        $this->initLang($chatId);
        $this->initUser($chatId);
        // 1. 回调查询（按钮）
        if ($update->isType('callback_query')) {
            $callback = $update->getCallbackQuery();
            $callbackData = $callback->getData();

            $parts = parse_url($callbackData);

            // 第二步：解析 query 参数
            $params = [];
            if (isset($parts['query'])) {
                parse_str($parts['query'], $params);
            }

            // 输出
            $path = $parts['path'];  // /foo
            // $params = ['bar' => 'ff', 'baz' => '123'];
            $this->handleCallbackQuery($path, $update, $params);
            return;
        }

        // 2. 普通消息（指令 or 文本）
        if ($update->isType('message')) {
            $message = $update->getMessage();
            $text = $message->getText();

            if (str_starts_with($text, '/')) {
                $command = explode(' ', $text)[0];
                $arr1 = explode('@', $command);
                if (count($arr1) > 1) {
                    $command = $arr1[0];
                }
                $arr2 = explode(' ', $command);
                if (count($arr2) > 1) {
                    $params = ['command_data' => $arr2[1]];
                    Context::set(self::QUERY_PARAMS_KEY, $params);
                }
                $this->handleCommand($command, $update);
            } else {
                $this->handleText($update, $text);
            }
        }
    }

    /**
     * 处理指令
     */
    protected function handleCommand(string $command, Update $update): void
    {
        Logger::info('handle Command: ' . $command);
        if ($command == '/start') {
            Logger::info('更新用户信息');
            $this->updateUserInfo($update);
        }
        $handler = AnnotationRegistry::getCommandHandler($command);
        if ($handler) {
            /** @var CommandInterface $instance */
            [$instance, $method] = $handler;
            call_user_func([$instance, $method], $this, $update);
        } else {
            Logger::info("未知命令：$command");
        }
    }

    private function isMenu($text): ?string
    {
        Logger::info("Text => $text");
        if (isset($this->menuMap[$text])) {
            Logger::info("Is Menu => {$this->menuMap[$text]}");
            return $this->menuMap[$text];
        }
        return null;
    }

    /**
     * 处理普通文本
     */
    protected function handleText(Update $update, $text)
    {
        if ($menu = $this->isMenu($text)) {
            $handler = AnnotationRegistry::getMenuHandler($menu);
            if ($handler) {
                [$class, $method] = $handler;
                /** @var QueryCallbackInterface $instance */
                $instance = make($class);
                Logger::info("Menu#$menu 处理器：$class");
                call_user_func([$instance, $method], $this, $update);
            } else {
                Logger::info("Menu#$menu 未定义处理器");
            }
        } else {
            $chatId = $this->getChatId($update);
            $state = $this->getState($chatId);
            if ($state) {
                Context::set('state', $state);
                $this->handleState($update, $state, $text);
            } else {
                Logger::info('handle Text: ' . $text);
                $this->telegram->sendMessage([
                    'chat_id' => $update->getMessage()->getChat()->getId(),
                    'text' => "你发送了：$text",
                ]);
            }
        }
    }

    /**
     * 处理回调查询（按钮点击）
     * @throws TelegramSDKException
     */
    protected function handleCallbackQuery(string $path, Update $update, array $params = []): void
    {
        Logger::info('handle query callback: ' . $path . ' params=' . json_encode($params));
        Context::set(self::QUERY_PARAMS_KEY, $params);
        $handler = AnnotationRegistry::getQueryCallbackHandler($path);
        if ($handler) {
            [$class, $method] = $handler;
            /** @var QueryCallbackInterface $instance */
            $instance = make($class);
            call_user_func([$instance, $method], $this, $update);
        } else {
            Logger::info("未知命令：$path");
        }
    }

    /**
     * @throws TelegramSDKException
     */
    public function reply(ReplyMessageInterface $msg): void
    {
        $msg->reply();
    }

    public function transMessage(string $key, array $params = []): string
    {
        $currentLang = LangContext::get();
        if (isset($this->messages[$currentLang])) {
            if (isset($this->messages[$currentLang][$key])) {
                $template = $this->messages[$currentLang][$key];
                if (!empty($params) && $template) {
                    foreach ($params as $key => $value) {
                        if ($value !== null) {
                            $template = str_replace(':' . $key, $value, $template);
                        }
                    }
                }
                return $template;
            }
        }
        return $key;
    }

    /**
     * @throws TelegramSDKException
     */
    public function sendMessage(array $msg): void
    {
        $this->telegram->sendMessage($msg);
    }

    /**
     * @throws TelegramSDKException
     */
    public function editMessageText(array $msg, Update $update): void
    {
        $messageId = $this->getMessageId($update);
        $msg['message_id'] = $messageId;
        Logger::info('修改消息：' . $messageId);
        $this->telegram->editMessageText($msg);
    }

    /**
     * 回复的文本国际化
     *
     * @param array $messages
     * @return void
     */
    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    public function setMenus(array $menus, mixed $menuMap)
    {
        $this->menus = $menus;
        $this->menuMap = $menuMap;
    }

    public function getAccessToken(): string
    {
        return $this->token;
    }

    public function getChatId(Update $update)
    {
        return $update->getMessage()->getChat()->getId();
    }

    public function getUsername(Update $update): ?string
    {
        return $update->getMessage()->getChat()->getUsername();
    }

    public function getNickname(Update $update): string
    {
        $chat = $update->getMessage()->getChat();
        $type = $chat->getType();

        switch ($type) {
            case 'private':
                return trim($chat->getFirstName() . ' ' . $chat->getLastName());

            case 'group':
            case 'supergroup':
            case 'channel':
                return $chat->getTitle(); // 群组/频道的名称

            default:
                return 'Unknown';
        }
    }

    public function trans(string $key, array $params = []): string
    {
        return $this->translator->trans($key, $params);
    }


    public function transButton(string $key, array $params = []): string
    {
        return $this->transMessage('buttons.' . $key, $params);
    }

    /**
     * @throws TelegramSDKException
     * @throws \RedisException
     */
    public function changeLanguage(Update $update): void
    {
        $chatId = $this->getChatId($update);
        $lang = $this->getQueryParam('lang', 'zh_CN');
        $this->redis->set("locale:{$this->botID}:$chatId", $lang);
        LangContext::set($lang);
        $this->translator->setLocale($lang);
        $this->answer($update, $this->transMessage('switch_language_success'));
    }

    public function getQueryParam(?string $key = null, $default = null): mixed
    {
        $params = Context::get(self::QUERY_PARAMS_KEY);
        if (!$key) return $params;
        if (isset($params[$key])) {
            return $params[$key];
        }
        return $default;
    }

    /**
     * @throws TelegramSDKException
     */
    public function answer(Update $update, ?string $text = null, bool $showAlert = false): bool
    {
        if ($callback = $update->getCallbackQuery()) {
            $callbackQueryId = $callback->getId();
            $params = [
                'callback_query_id' => $callbackQueryId,
            ];
            if ($text !== null) {
                $params['text'] = $text;
                $params['show_alert'] = $showAlert;
            }
            Logger::info('AnswerCallbackQuery: ' . json_encode($params));
            $resp = $this->telegram->answerCallbackQuery($params);
            Logger::info('answerCallbackQuery: ' . $resp);
            return $resp;
        }
        return false;
    }

    public function getKeyboards(): array
    {
        return $this->keyboards;
    }


    public function getBotID(): int
    {
        return $this->botID;
    }

    public function setKeyboards(array $keyboards): void
    {
        $this->keyboards = $keyboards;
    }

    public function setState(int $chatId, string $key, mixed $value = null, int $expiresIn = 0): void
    {
        $redisKey = "trc20:state:{$this->botID}:$chatId";
        $this->redis->set($redisKey, json_encode(['key' => $key, 'value' => $value]));
        $this->redis->expire($redisKey, $expiresIn);
    }

    public function getState(int $chatId): ?StateEntity
    {
        $res = $this->redis->get("trc20:state:{$this->botID}:$chatId");
        if ($res) {
            $arr = json_decode($res, true);
            return StateEntity::of($arr);
        }
        return null;
    }

    /**
     * @throws \RedisException
     */
    public function endState(Update $update): void
    {
        $chatId = $this->getChatId($update);
        $this->redis->del("trc20:state:{$this->botID}:$chatId");
    }

    /**
     * 多步骤交互流程
     *
     * @param Update $update
     * @param mixed $state
     * @param $text
     * @return void
     */
    private function handleState(Update $update, StateEntity $state, $text): void
    {
        Logger::info('Handle State ' . json_encode($state));
        $handler = AnnotationRegistry::getStateHandler($state->key);
        if ($handler) {
            /** @var StateHandlerInterface $instance */
            [$instance, $method] = $handler;
            $class = get_class($instance);
            Logger::info("Found State Handler $class@$method");
            call_user_func([$instance, $method], $this, $update, $state, $text);
        } else {
            Logger::info("未知命令：{$state->key}");
        }
    }

    public function getMessageId(Update $update): int
    {
        $message = $update->getCallbackQuery()->getMessage();
        $messageId = $message->getMessageId(); // 或 ->getId()，看 SDK
        return $messageId;
    }

    /**
     * @throws TelegramSDKException
     */
    public function delMessage(Update $update, $messageId = null): void
    {
        $this->telegram->deleteMessage([
            'chat_id' => $this->getChatId($update),
            'message_id' => $messageId ?: $this->getMessageId($update),
        ]);
    }

    public function initLang(mixed $user_id): void
    {
        $language = $this->getUserLanguage($user_id);
        LangContext::set($language);
        $this->translator->setLocale($language);
    }

    private function initUser($chatId): void
    {
        $botId = $this->botID;
        $user = TelegramUser::where('user_id', $chatId)->where('bot_id', $botId)->first();
        if ($user) {
            Context::set(self::USER_KEY, $user);
        }
    }

    public function getCurrentUser(): ?TelegramUser
    {
        return Context::get(self::USER_KEY);
    }

    private function updateUserInfo(Update $update): void
    {
        $chatId = $this->getChatId($update); // 消息来自群里面的机器人时，这个chatId变成群ID了，
        $botToken = $this->getAccessToken();
        $arr = explode(":", $botToken);
        $botId = $arr[0];
        $userInfo = [
            'bot_id' => $botId,
            'user_id' => $chatId,
            'username' => $this->getUsername($update),
            'nickname' => $this->getNickname($update),
        ];
        $getAvatarHandle = config('telegram.get_avatar');
        $userInfo['avatar'] = $getAvatarHandle ? $getAvatarHandle($update) : $this->getAvatar($update);
        $user = TelegramUser::updateOrCreate([
            'bot_id' => $botId,
            'user_id' => $chatId,
        ], $userInfo);
        Logger::info('同步用户信息成功：' . $user->id . ' => ' . json_encode($userInfo));
    }

    private function getAvatar(Update $update): string
    {
        try {
            Logger::debug("开始获取头像");
            $userId = $update->getMessage()->from->id;

            $photos = $this->telegram->getUserProfilePhotos([
                'user_id' => $userId,
                'limit' => 1,
            ]);

            if ($photos->total_count > 0) {
                $fileId = $photos->photos[0][0]['file_id'];
                $file = $this->telegram->getFile(['file_id' => $fileId]);
                $filePath = $file->file_path;
                $avatarUrl = "https://api.telegram.org/file/bot" . $this->telegram->getAccessToken() . "/" . $filePath;
                Logger::debug("用户头像地址: " . $avatarUrl);
                return $avatarUrl;
            } else {
                Logger::debug("用户没有头像");
            }
        } catch (\Exception $e) {
            Logger::error("获取头像异常：{$e->getMessage()}");
        }
        return "";
    }
}