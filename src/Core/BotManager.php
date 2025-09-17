<?php
declare(strict_types=1);

namespace William\HyperfExtTelegram\Core;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use William\HyperfExtTelegram\Config;
use William\HyperfExtTelegram\Core\Annotation\AnnotationRegistry;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramBot;
use function Hyperf\Support\make;

class BotManager
{
    protected array $bots = [];
    protected bool $isSubscribed = false;

    protected Redis $redis;
    protected \Psr\Log\LoggerInterface $logger;
    protected \Swoole\Coroutine\Server|\Swoole\Server $server;
    protected int $workerId = 0;
    protected bool $running;
    protected ClientFactory $clientFactory;
    protected TranslatorInterface $translator;
    protected array $languages = ['en', 'zh-CN', 'zh-TW'];
    /**
     * @var array|string[]
     */
    protected array $menus = [];
    /**
     * @var array|mixed
     */
    protected array $menusLanguages = [];
    protected array $keyboards = [];
    /**
     * @var array|mixed
     */
    protected array $menuMap = [];
    protected array $messages = [];
    /**
     * @var int[]|string[]
     */
    protected array $buttons = [];

    public function __construct(protected ContainerInterface $container)
    {
        $this->redis = $this->container->get(RedisFactory::class)->get('default');
        $this->logger = $this->container->get(LoggerFactory::class)->get('bot');
        $this->clientFactory = $this->container->get(ClientFactory::class);
        $this->translator = $this->container->get(TranslatorInterface::class);
        $this->running = true;
        AnnotationRegistry::register();
    }

    public function getBot($token): Instance
    {
        return $this->bots[$token];
    }

    public function isRunning($token): bool
    {
        return isset($this->bots[$token]);
    }

    public function start(): void
    {
        $mode = \Hyperf\Support\env('TELEGRAM_MODE');
        $env = \Hyperf\Support\env('APP_ENV');
        Logger::debug("当前环境：$env 模式 $mode");

        if ($mode == 'webhook') {
            $this->startWebhook();
        } else {
            if (\Hyperf\Support\env('APP_ENV') != 'production') {
                $token = \Hyperf\Config\config('telegram.dev_token');
                Logger::debug("启动测试机器人 $token");
                $bot = TelegramBot::updateOrCreate(['token' => $token]);
                $this->startPulling($bot);
            } else {
                $bots = TelegramBot::all();
                foreach ($bots as $bot) {
                    $this->startPulling($bot);
                }
            }
        }
    }

    public function addBot($token, $language = 'zh_CN'): TelegramBot
    {
        $mode = \Hyperf\Support\env('TELEGRAM_MODE');
        $env = \Hyperf\Support\env('APP_ENV');
        Logger::debug("当前环境：$env 模式 $mode");
        $bot = TelegramBot::updateOrCreate(['token' => $token], ['language' => $language]);
        $instance = $this->newInstance($bot);
        if ($mode == 'webhook') {
            $instance->sync();
            $instance->webhook();
        } else {
            $this->startPulling($bot);
        }
        return $bot;
    }

    public function startWebhook(): void
    {
        $bots = TelegramBot::all();
        foreach ($bots as $bot) {
            $instance = $this->newInstance($bot);
            $instance->start(isset($this->bots[$bot->token]), 'webhook');
        }
    }

    public function startPulling(TelegramBot $bot, bool $async = true): void
    {
        Logger::info("Worker#{$this->workerId} 启动机器人 {$bot->token} ...");
        if (isset($this->bots[$bot->token])) {
            $this->logger->info("Bot {$bot->token} already running.");
            return;
        }
        if ($async) {
            Coroutine::create(function () use ($bot) {
                $this->polling($bot->token, $bot);
            });
        } else {
            $this->polling($bot->token, $bot);
        }
    }

    public function polling(string $token, TelegramBot $bot): void
    {
        $this->logger->info("Worker#{$this->workerId} Starting bot: $token");
        $instance = $this->newInstance($bot);
        $instance->start(isset($this->bots[$token]));
        Logger::info("Worker#{$this->workerId} 关闭机器人：" . $token);
    }

    public function stopBot(TelegramBot $bot): void
    {
        $this->logger->info("Stopping bot $bot->username ...");
        /** @var Instance $instance */
        if (isset($this->bots[$bot->token])) {
            $instance = $this->bots[$bot->token];
            $instance->stop();
        } else {
            Logger::debug("关闭机器人失败：{$bot->username} 未运行");
        }
    }

    public function getWorkerId()
    {
        return $this->workerId;
    }

    /**
     * @throws \RedisException
     */
    public function init(int $workerId): void
    {
        $this->workerId = $workerId;
        $this->logger->debug('初始化管理器：' . $workerId);
        $this->redis->sAdd('tg_bot:workers', $this->workerId);
        $this->_initLanguage();
        $this->listen($workerId);
    }

    /**
     * @throws \RedisException
     */
    public function dispatch($token, $event, $extra = []): void
    {
        Logger::info("Event#$event => $token");
        if (str_contains($token, ':')) {
            $arr = explode(':', $token);
            $botId = (int)$arr[0];
        } else {
            $botId = (int)$token;
        }
        $workerId = $this->getWorkerByMod($botId);
        Logger::info("Dispatch#$workerId");
        $this->sendEvent($workerId, $event, $token, $extra);
    }

    public function sendEvent($workerId, $event, $data, $extra = []): void
    {
        $queue = 'tg_bot:event_queue:' . $workerId;
        $params = array_merge($extra, ['token' => $data, 'event' => $event]);
        $this->redis->lPush($queue, json_encode($params));
    }

    public function getWorkerByMod(int $targetId): int
    {
        $workers = $this->redis->sMembers('tg_bot:workers');

        if (empty($workers)) {
            return $this->workerId; // 默认回退
        }

        // 排序是为了确保取模后索引对应的 worker 是稳定的
        sort($workers);

        $index = $targetId % count($workers);

        return (int)$workers[$index];
    }

    /**
     * @throws \RedisException
     */
    public function shutdown()
    {
        Logger::info("Worker#{$this->workerId} 关闭BotManager...");
        $this->running = false;
        $this->bots = [];
        // 可选：清理 Redis 中的 worker 注册信息
        $this->redis->sRem('tg_bot:workers', $this->workerId);
        $bots = $this->redis->sMembers('tg_bot:worker:' . $this->workerId);
        if (!empty($bots)) {
            foreach ($bots as $token) {
                if ($model = TelegramBot::where('token', $token)->first()) {
                    $model->status = 'stopped';
                    $model->save();
                }
                $this->redis->sRem('tg_bot:worker:' . $this->workerId, $token);
            }
            $this->redis->del('tg_bot:worker:' . $this->workerId);
        }
    }

    private function _initLanguage(): void
    {
        Logger::info("BotManager#{$this->workerId}初始化语言");
        $this->languages = Config::languages();
        $this->_translate();
    }

    private function _translate(): void
    {
        $this->menus = Config::menus();
        $this->buttons = Config::buttons();
        $this->menusLanguages = [];
        $this->keyboards = [];
        $this->menuMap = [];
        foreach ($this->languages as $language) {
            $this->keyboards[$language] = [];
            $this->translator->setLocale($language);
            $this->_translates($language);
            foreach ($this->menus as $i => $row) {
                $this->keyboards[$language][$i] = [];
                foreach ($row as $item) {
                    $menuKey = 'menu.' . $item;
                    $text = $this->translator->trans($menuKey);
                    $this->menuMap[$text] = $item;
                    $this->keyboards[$language][$i][] = ['text' => $text];
                    if (!isset($this->menusLanguages[$language])) {
                        $this->menusLanguages[$language] = [];
                    }
                    $this->menusLanguages[$language][$menuKey] = $text;
                }
            }
        }
    }

    private function _translates(string $language): void
    {
        if (!isset($this->messages[$language])) {
            $this->messages[$language] = [];
        }
        foreach (Config::messages() as $key) {
            $text = $this->translator->trans("message." . $key);
            $this->messages[$language][$key] = $text;
        }

        foreach (Config::buttons() as $keyButton) {
            $text = $this->translator->trans("buttons." . $keyButton);
            $this->messages[$language]["buttons." . $keyButton] = $text;
        }
//        Logger::info("BotManager#{$this->workerId} 翻译 {$language} => ".json_encode($this->messages));
    }

    public function getMessage(string $key, array $params = []): string
    {
        $currentLang = LangContext::get();
        if (isset($this->messages[$currentLang])) {
            if (isset($this->messages[$currentLang][$key])) {
                $template = $this->messages[$currentLang][$key];
                if (!empty($params)) {
                    foreach ($params as $key => $value) {
                        $template = str_replace(':' . $key, $value, $template);
                    }
                }
                return $template;
            }
        }
        return $key;
    }

    private function listen($workerId): void
    {
        Logger::info('Start Listen on worker ' . $workerId . ' is running: ' . $this->running);
        Coroutine::create(function () use ($workerId) {
            while ($this->running) {
                try {
                    $queueName = 'tg_bot:event_queue:' . $workerId;
                    $res = $this->redis->lPop($queueName);
                    if ($res) {
                        Logger::info("Queue#$queueName#$workerId: on message <= $res");
                        $event = json_decode($res);
                        $this->handleEvent($event);
                    } else {
                        Coroutine::sleep(0.5); // 避免空转
                    }
                } catch (\Throwable $e) {
                    $this->logger->error("Redis listen error: " . $e->getMessage());
                    Coroutine::sleep(3);
                }
            }
            Logger::info("Worker#{$this->workerId} 关闭消费者 tg_bot:start_queue:$workerId");
        });
    }

    private function handleEvent($event): void
    {
        $handler = AnnotationRegistry::getEventHandler($event->event);
        if ($handler) {
            [$class, $method] = $handler;
            /** @var QueryCallbackInterface $instance */
            $instance = make($class);
            Logger::info("Event#{$event->event} 处理器：$class");
            call_user_func([$instance, $method], $this, $event);
        } else {
            Logger::info("Event#{$event->event} 未定义处理器");
        }
    }

    private function newInstance(TelegramBot $bot): Instance
    {
        $instance = new Instance($bot);
        $instance->setMessages($this->messages);
        $instance->setMenus($this->menus, $this->menuMap);
        $instance->setKeyboards($this->keyboards);

        $this->redis->sAdd('tg_bot:workers', $this->workerId);
        $this->redis->sAdd('tg_bot:worker:' . $this->workerId, $bot->token);
        $this->bots[$bot->token] = $instance;
        $bot->status = 'running';
        $bot->save();
        return $instance;
    }

    public function startBot(TelegramBot $bot)
    {
        Logger::info('正在启动机器人' . $bot->username);
        $this->addBot($bot->token, $bot->language);
    }
}
