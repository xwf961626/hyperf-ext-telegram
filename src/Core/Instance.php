<?php

namespace William\HyperfExtTelegram\Core;

use Hyperf\Cache\Cache;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Chat;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Objects\User;
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
    private $running = true;
    private $mode = 'pulling';
    protected Cache $cache;
    private $states = [];

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->token = $bot->token;
        $arr = explode(':', $bot->token);
        $this->botID = (int)$arr[0];
        $this->redis = ApplicationContext::getContainer()->get(RedisFactory::class)->get('default');
        $this->cache = make(Cache::class);
        $this->clientFactory = ApplicationContext::getContainer()->get(ClientFactory::class);
        $this->translator = ApplicationContext::getContainer()->get(TranslatorInterface::class);
        $this->init();
    }

    public function getBot(): TelegramBot
    {
        return $this->bot;
    }

    /**
     * @throws TelegramSDKException
     */
    private function init(): void
    {
        $telegram = TelegramBotFactory::create($this->clientFactory, $this->token, [],
            \Hyperf\Support\env('TG_ENDPOINT', 'https://api.telegram.org'));
        $this->telegram = $telegram;
    }

    public function setCommands(): void
    {
        $commands = config('telegram.commands');
        Logger::debug("set commands => " . json_encode($commands, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->telegram->setMyCommands([
            'commands' => $commands
        ]);
    }

    public function webhook(bool $condition = true): void
    {
        $this->mode = 'webhook';
        Logger::debug("instance starting webhook...");
        if ($condition) {
            $sign = md5($this->bot->id . $this->bot->token . time() . random_bytes(10));
            $url = \Hyperf\Support\env('BOT_WEBHOOK_BASE');
            $url = rtrim($url, '/');
            $url = "{$url}/{$this->bot->id}/{$sign}";
            Logger::debug("添加webhook:" . $url);
            $this->telegram->setWebhook([
                'url' => $url,
            ]);
            $this->setCommands();
            ApplicationContext::getContainer()->get(Cache::class)->set("webhook_token:" . $this->bot->id, $sign);
            $this->running = true;
        }
    }

    public function pulling(bool $condition): void
    {
        $offset = 0;
        $this->telegram->deleteWebhook();
        $this->setCommands();
        $this->running = true;
        Logger::debug("开始 pulling...");
        while ($this->running) {
            try {
                $updates = $this->telegram->getUpdates([
                    'offset' => $offset,
                    'limit' => 100,
                    'timeout' => 3,
                    'connect_timeout' => 5,
                ]);
                foreach ($updates as $update) {
                    $offset = $update['update_id'] + 1;
                    $lockKey = "telegram:update_lock:{$update->getChat()->id}";
                    Logger::debug("update lock: $lockKey");
                    $lockTtl = 300; // 秒，锁有效期（5分钟）
                    // 1. Redis锁防止并发重复
                    $isFirst = $this->redis->set($lockKey, 1, ['NX', 'EX' => $lockTtl]);
                    if (!$isFirst) {
                        Logger::debug("跳过频繁的update id: {$update['update_id']}");
                        continue;
                    }
                    \Hyperf\Coroutine\go(function () use ($update, $lockKey) {
                        try {
                            $this->handleUpdate($update);
                        } catch (RuntimeError $e) {
                            try {
                                if ($errorHandler = ApplicationContext::getContainer()->get(ErrorHandlerFactory::class)->get($e->getMessage())) {
                                    $handlerClass = get_class($errorHandler);
                                    Logger::debug("Error {$e->getMessage()} handled by {$handlerClass}.");
                                    $errorHandler->notify($this, $update, $e->getExtra());
                                } else {
                                    Logger::error("未定义的错误处理器 {$e->getMessage()}");
                                }
                            } catch (\Exception $e) {
                                Logger::error("pulling 异常 {$e->getMessage()}");
                            }
                        } catch (\Throwable $e) {
                            Logger::info("handleUpdate未知异常:" . $e->getMessage() . $e->getTraceAsString());
                        } finally {
                            $this->redis->del($lockKey);
                        }
                    });
                }
            } catch (\Throwable $e) {
                Logger::info("getUpdates未知异常:" . $e->getMessage() . $e->getTraceAsString());
            }
        }
        Logger::debug("机器人结束Pulling");
    }

    public function sync()
    {
        $me = $this->telegram->getMe();
        Logger::info("bot getMe => " . json_encode($me, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        TelegramBot::where(['token' => $this->token])->update(['username' => $me->username, 'nickname' => $me->firstName]);
        $key = 'bot:' . $this->token;
        $this->redis->del($key);
    }

    public function start(bool $condition = true, string $method = 'pulling'): void
    {
        $this->sync();
        $this->mode = $method;
        call_user_func([$this, $method], $condition);
    }

    public function getBotCache(): array
    {
        $key = 'bot:' . $this->token;
        if (!$this->redis->exists($key)) {
            $botCache = TelegramBot::where('token', $this->token)->first();
            $this->redis->setex($key, 3600, json_encode($botCache));
            return $botCache->toArray();
        }
        return json_decode($this->redis->get($key), true);
    }

    private function getUserLanguage($chatId)
    {
        $locale = $this->redis->get('locale:' . $this->botID . ':' . $chatId);
        if (!$locale) {
            $botCache = $this->getBotCache();
            $locale = $botCache['language'] ?? 'zh_CN';
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
        Logger::info("Telegram update => " . json_encode($update, JSON_UNESCAPED_UNICODE));
        $chat = $update->getChat();
        $chatId = $chat->id;
        Logger::info("chat id => {$chatId}, chat title => {$chat->title}");
        $this->initLang($chatId);

        if ($update->isType('my_chat_member')) { // 进群
            Logger::debug("my_chat_member...");
            $event = null;
            if ($update->myChatMember->newChatMember->status == 'member' && $update->myChatMember->oldChatMember->status == 'left') {
                $event = Events::EVENT_BOT_PULL_INTO_GROUP;
            } elseif ($update->myChatMember->newChatMember->status == 'kicked' && $update->myChatMember->oldChatMember->status == 'member') {
                $event = Events::EVENT_BOT_BLOCKED;
            } elseif ($update->myChatMember->newChatMember->status == 'member' && $update->myChatMember->oldChatMember->status == 'kicked') {
                $event = Events::EVENT_BOT_UNBLOCKED;
            }
            if ($event) {
                $this->onEvent($update, $event);
            }
        }

        if (!empty($update->chatMember)) {
            Logger::debug("chatMember...");
            $event = null;
            if ($update->chatMember->newChatMember->status == 'member' && $update->chatMember->oldChatMember->status == 'left') {
                $event = Events::EVENT_USER_INVITED_TO_GROUP;
            } elseif ($update->chatMember->newChatMember->status == 'kicked' && $update->chatMember->oldChatMember->status == 'member') {
                $event = Events::EVENT_USER_KICKED_FROM_GROUP;
            } elseif ($update->chatMember->newChatMember->status == 'administrator' && $update->chatMember->oldChatMember->status == 'member') {
                $event = Events::EVENT_USER_SET_ADMIN;
            } elseif ($update->chatMember->newChatMember->status == 'left' && $update->chatMember->oldChatMember->status == 'member') {
                $event = Events::EVENT_USER_LEFT_GROUP;
            }
            if ($event) {
                $this->onEvent($update, $event);
            }
        }

        // 表示 用户申请加入群/频道（即开启了 “加入需审批” 功能的群）
        if (!empty($update->chatJoinRequest)) {
            $this->onEvent($update, Events::EVENT_CHAT_JOIN_REQUEST);
        }

        // 1. 回调查询（按钮）
        if ($update->isType('callback_query')) {
            $this->initUser($chatId, $update);
            $callback = $update->getCallbackQuery();
            $callbackDataKey = $callback->getData();
            Logger::debug("on callback query <= " . $callbackDataKey);
            $callbackData = $this->cache->get($callbackDataKey);
            if (!$callbackData) {
                throw new RuntimeError("Update has expired");
            }
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
            $this->initUser($chatId, $update);
            $message = $update->getMessage();
            $text = $message->getText();
            if ($text) {
                if (str_starts_with($text, '/')) {
                    $commands = explode(' ', $text);
                    $command = $commands[0];
                    $arr1 = explode('@', $command);
                    if (count($arr1) > 1) {
                        $command = $arr1[0];
                    }
                    if (isset($commands[1])) {
                        $arr2 = explode('_', $commands[1]);
                        if (!empty($arr2)) {
                            $params = ['command_data' => $arr2];
                            Context::set(self::QUERY_PARAMS_KEY, $params);
                        }
                    }

                    $this->handleCommand($command, $update);
                } else {
                    $this->handleText($update, $text);
                }
            } else {
                $this->handleStateAnyway($update, $text);
            }
        }
    }

    /**
     * 处理指令
     */
    protected function handleCommand(string $command, Update $update): void
    {
        Logger::info('handle Command: ' . trim($command));
        if ($command == '/start') {
            Logger::info('更新用户信息');
            $this->updateUserInfo($update);
        }

        $handlerConfig = config('telegram.command_handlers');
        Logger::debug('command_handlers config =>' . json_encode($handlerConfig));
        $handler = null;
        if (isset($handlerConfig[$command])) {
            $instance = make($handlerConfig[$command]);
            $handler = [$instance, 'handle'];
        }
        if (!$handler) {
            $handler = AnnotationRegistry::getCommandHandler($command);
        }
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
            $this->handleStateAnyway($update, $text);
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
        $handlerConfig = config('telegram.callback_handlers');
        $handler = null;
        if (isset($handlerConfig[$path])) {
            $handler = [$handlerConfig[$path], 'handle'];
        }
        if (!$handler) {
            $handler = AnnotationRegistry::getQueryCallbackHandler($path);
        }
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

    public function startState(int $chatId, string $name, int $ttl = 0): StateBus
    {
        $state = new StateBus($chatId, $name, $ttl);
        $this->states[$chatId] = $state;
        return $state;
    }

    public function setState(int $chatId, string $name, string $value = ''): void
    {
        $state = $this->getState($chatId);
        $state->add($name, $value);
        $this->states[$chatId] = $state;
    }

    public function getState(int $chatId): ?StateBus
    {
        if (!isset($this->states[$chatId])) {
            if ($this->redis->exists(StateBus::STATE_KEY_PREFIX . $chatId)) {
                $this->states[$chatId] = new StateBus($chatId);
            }
        }
        return $this->states[$chatId] ?? null;
    }

    public function endState(Update $update): void
    {
        $chatId = $this->getChatId($update);
        if ($state = $this->getState($chatId)) {
            $state->end();
            unset($this->states[$chatId]);
        }
    }

    /**
     * 多步骤交互流程
     *
     * @param Update $update
     * @param mixed $state
     * @param $text
     * @return void
     */
    private function handleState(Update $update, StateBus $state, $text): void
    {
        Logger::info('Handle State ' . json_encode($state));
        $handler = AnnotationRegistry::getStateHandler($state->get("name"));
        if ($handler) {
            /** @var StateHandlerInterface $instance */
            [$instance, $method] = $handler;
            $class = get_class($instance);
            Logger::info("Found State Handler $class@$method");
            call_user_func([$instance, $method], $this, $update, $state, $text);
        } else {
            Logger::info("未知命令：{$state->get("name")}");
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

    private function initUser($chatId, $update): void
    {
        $botId = $this->bot->id;
        $user = TelegramUser::where('user_id', $chatId)->where('bot_id', $botId)->first();
        if ($user) {
            Context::set(self::USER_KEY, $user);
        } else {
            $this->updateUserInfo($update);
        }
    }

    public function getCurrentUser(): ?TelegramUser
    {
        return Context::get(self::USER_KEY);
    }

    private function updateUserInfo(Update $update): void
    {
        $chatId = $this->getChatId($update); // 消息来自群里面的机器人时，这个chatId变成群ID了，
        $botId = $this->bot->id;
        $userInfo = [
            'bot_id' => $botId,
            'user_id' => $chatId,
            'username' => $this->getUsername($update),
            'nickname' => $this->getNickname($update),
        ];
        $getAvatarHandle = config('telegram.get_avatar');
        $avatarCache = $this->cache->get('avatars:' . $chatId);
        if (!$avatarCache) {
            $userInfo['avatar'] = $getAvatarHandle ? $getAvatarHandle($update) : $this->getAvatar($update);
            $this->cache->set('avatars:' . $chatId, $userInfo['avatar'], 600);
        }
        $user = TelegramUser::updateOrCreate([
            'bot_id' => $botId,
            'user_id' => $chatId,
        ], $userInfo);
        Logger::info('同步用户信息成功：' . $user->id . ' => ' . json_encode($userInfo));
        Context::set(self::USER_KEY, $user);
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
                return $this->saveAvatar($avatarUrl, $userId);
            } else {
                Logger::debug("用户没有头像");
            }
        } catch (\Exception $e) {
            Logger::error("获取头像异常：{$e->getMessage()}");
        }
        return "";
    }

    private function saveAvatar($avatarUrl, $userId)
    {
        // 保存路径（确保目录存在）
        $saveDir = BASE_PATH . '/' . config('telegram.store_dir') . '/avatars/';
        $savePath = $saveDir . $userId . '.jpg';

// 创建目录（如果不存在）
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0777, true);
        }

// 下载文件
        $avatarData = file_get_contents($avatarUrl);

        if ($avatarData === false) {
            throw new \Exception("Failed to download avatar from Telegram.");
        }

// 保存文件
        $fileSaved = file_put_contents($savePath, $avatarData);

        if ($fileSaved === false) {
            throw new \Exception("Failed to save avatar to local path: {$savePath}");
        }

        Logger::debug("Avatar saved successfully: {$savePath}");
        return config('telegram.store_dir') . '/avatars/' . $userId . '.jpg';
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function stop(): void
    {
        Logger::debug("正在关闭机器人...");
        if ($this->isRunning()) {
            Logger::debug("关闭成功");
            $this->running = false;
            Logger::debug("运行模式：{$this->mode}");
            if ($this->mode === 'webhook') {
                Logger::debug("删除webhook");
                $this->telegram->deleteWebhook();
            }
        }
    }

    private function handleCommonText(Update $update, $text)
    {
        if ($handler = config('telegram.common_text_handler')) {
            if (class_exists($handler)) {
                $handlerIns = new $handler($this, $update);
                $handlerIns->handle($text);
            } else {
                Logger::error("文本处理类不存在：$handler");
            }
        }
    }

    private function handleStateAnyway($update, $text)
    {
        $chatId = $this->getChatId($update);
        $state = $this->getState($chatId);
        if ($state) {
            Context::set('state', $state);
            $this->handleState($update, $state, $text);
        } else {
            Logger::info('handle Text: ' . $text);
            $this->handleCommonText($update, $text);
        }
    }

    private function onEvent(Update $update, string $event)
    {
        $listener = AnnotationRegistry::getEventListener($event);
        if ($listener) {
            /** @var Listener $instance */
            [$instance, $method] = $listener;
            $class = get_class($instance);
            Logger::info("Found Event Listener $class@$method");
            call_user_func([$instance, $method], $this, $update);
        } else {
            Logger::info("无监听事件：" . $event);
        }
    }
}