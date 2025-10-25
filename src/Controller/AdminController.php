<?php

namespace William\HyperfExtTelegram\Controller;

use Hyperf\HttpServer\Request;
use Hyperf\HttpServer\Router\Router;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Swoole\Table;
use William\HyperfExtTelegram\Core\BotManager;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramBot;
use William\HyperfExtTelegram\Model\TelegramUser;
use function Hyperf\Config\config;
class AdminController extends BaseController
{

    protected Redis $redis;

    public function __construct(protected BotManager $botManager, RedisFactory $redisFactory)
    {
        parent::__construct();
        $this->redis = $redisFactory->get('default');
    }

    protected function setCommand($cmd, $botId)
    {
        $this->redis->set('robot_command', json_encode([
            'command' => $cmd,
            'botId' => $botId,
        ]));
    }

    public static function addRoutes(): void
    {
        Router::post('telegram/bots', [self::class, 'addTelegramBot']);
        Router::delete('telegram/bots/{id}', [self::class, 'deleteTelegramBot']);
        Router::put('telegram/bots/{id}', [self::class, 'editTelegramBot']);
        Router::get('telegram/bots', [self::class, 'getTelegramBots']);

        Router::get('telegram/users', [self::class, 'getTelegramUsers']);
    }

    public function addTelegramBot(Request $request)
    {
        if (!$token = $request->post('token')) {
            return $this->error(config('telegram.validate_messages')['telegram token is required']);
        }
        $language = $request->post('language', 'zh_CN');
        try {
            $bot = TelegramBot::updateOrCreate(['token' => $token], ['language' => $language, 'status' => 'stopped']);
            $this->setCommand('add', $bot->id);
            return $this->success($bot);
        } catch (\Exception $e) {
            Logger::error($e->getMessage());
            return $this->error(config('telegram.validate_messages')['telegram token is invalid']);
        }
    }

    public function getTelegramBots(Request $request)
    {
        $query = TelegramBot::query();
        if ($keywords = $request->query('keywords')) {
            $query = $query->where('username', 'like', '%' . $keywords . '%')
                ->orWhere('nickname', 'like', '%' . $keywords . '%');
        }
        $results = $query->orderBy('id', 'desc')
            ->paginate($request->query('limit', 15));
        return $this->success($results);
    }


    public function editTelegramBot(int $id, Request $request)
    {
        $bot = TelegramBot::find($id);
        if (!$bot) {
            return $this->error(config('telegram.validate_messages')['telegram token not found']);
        }
        try {
            $oldStatus = $bot->status;
            $bot->update($request->post());
            if ($this->redis->exists('bot:' . $bot->token)) {
                $this->redis->del('bot:' . $bot->token);
            }
            $bot->refresh();
            if ($oldStatus === 'running' && $bot->status === 'stopped') {
                Logger::info("管理员关闭机器人");
                $this->setCommand('stop', $bot->id);
            }
            if ($oldStatus === 'stopped' && $bot->status === 'running') {
                Logger::info("管理员开启机器人");
                $this->setCommand('start', $bot->id);
            }
        } catch (\Exception $e) {
            Logger::error($e->getMessage());
            return $this->error($e->getMessage());
        }
        return $this->success($bot);
    }

    public function deleteTelegramBot(int $id, Request $request)
    {
        $bot = TelegramBot::find($id);
        if (!$bot) {
            return $this->error(config('telegram.validate_messages')['telegram token not found']);
        }
        try {
            if ($bot->delete()) {
                $this->setCommand('stop', $bot->id);
                TelegramUser::where('bot_id', $bot->id)->delete();
            }
        } catch (\Exception $e) {
            Logger::error($e->getMessage());
            return $this->error($e->getMessage());
        }
        return $this->success(true);
    }

}