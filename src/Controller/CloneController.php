<?php

namespace William\HyperfExtTelegram\Controller;

use Carbon\Carbon;
use Hyperf\HttpServer\Router\Router;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Telegram\Bot\Api;
use William\HyperfExtTelegram\Core\BotManager;
use William\HyperfExtTelegram\Middleware\CloneMiddleware;
use William\HyperfExtTelegram\Model\TelegramBot;
use function Hyperf\Translation\trans;

class CloneController extends BaseController
{
    protected Redis $redis;

    public static function registerCloneRoutes()
    {
        Router::addGroup('/clone/bot', function () {
            Router::post('/add', [self::class, 'add']);
            Router::post('/update_token/{id}', [self::class, 'updateToken']);
            Router::post('/update_admins/{id}', [self::class, 'updateAdmins']);
            Router::get('/start/{id}', [self::class, 'start']);
            Router::get('/stop/{id}', [self::class, 'stop']);
            Router::get('/restart/{id}', [self::class, 'restart']);
            Router::get('/delete/{id}', [self::class, 'delete']);
            Router::get('/status/{id}', [self::class, 'status']);
            Router::post('/update_use_time/{id}', [self::class, 'updateUseTime']);
        }, ['middleware' => [CloneMiddleware::class]]);

    }

    public function __construct(protected BotManager $botManager, RedisFactory $redisFactory)
    {
        parent::__construct();
        $this->redis = $redisFactory->get('default');
    }

    protected function error($message, $code = 500)
    {
        return $this->response
            ->withStatus($code) // 设置状态码
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream(json_encode(['code' => $code, 'msg' => $message, 'flag' => false])));
    }

    protected function success($data = null)
    {
        return $this->response->withStatus(200)->withHeader('Content-Type', 'application/json')
            ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream(json_encode(['code' => 200, 'data' => $data, "msg" => "操作成功", 'flag' => true])));
    }

    public function add()
    {
        if (!$token = $this->request->post('token')) {
            return $this->error(trans('telegram token is required'));
        }
        if (!$expiredTime = $this->request->post('expiredTime')) {
            return $this->error(trans('expired time is required'));
        }
        $expiredTime = intval($expiredTime / 1000);
        if (!$admins = $this->request->post('admins')) {
            return $this->error(trans('admins is required'));
        }
        if (!$kefu = $this->request->post('kefu')) {
            return $this->error(trans('kefu is required'));
        }

        try {
            $me = (new Api($token))->getMe();
            $bot = TelegramBot::where('token', $token)->first();
            if ($bot) {
                return $this->error(trans('telegram bot already exists'));
            }
            $bot = new TelegramBot();
            $bot->id = $me->id;
            $bot->token = $token;
            $bot->expired_time = $expiredTime;
            $bot->expired_at = Carbon::createFromTimestamp($expiredTime);
            $bot->admins = $admins;
            $bot->username = $me->username;
            $bot->nickname = $me->firstName . ' ' . $me->lastName;
            $bot->kefu = $kefu;
            $bot->save();
            $this->setCommand('add', $bot->id);
            return $this->success($bot);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function updateToken($id)
    {
        try {
            $bot = TelegramBot::find($id);
            if (!$bot) {
                return $this->error(trans('telegram bot not found'));
            }
            if (!$token = $this->request->post('token')) {
                return $this->error(trans('token is required'));
            }
            if (TelegramBot::where('token', $token)->exists()) {
                return $this->error(trans('token already exists'));
            }
            $this->setCommand('updateToken', $bot->id, $token);

            return $this->success($bot);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function updateAdmins($id)
    {
        try {
            $bot = TelegramBot::find($id);
            if (!$bot) {
                return $this->error(trans('telegram bot not found'));
            }
            if (!$admins = $this->request->input('admins')) {
                return $this->error(trans('token is required'));
            }

            $bot->admins = $admins;
            $bot->save();

            return $this->success($bot);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function start($id)
    {
        try {
            $bot = TelegramBot::find($id);
            if (!$bot) {
                return $this->error(trans('telegram bot not found'));
            }

            $this->setCommand('start', $bot->id);

            return $this->success(['status' => 'running']);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function stop($id)
    {
        try {
            $bot = TelegramBot::find($id);
            if (!$bot) {
                return $this->error(trans('telegram bot not found'));
            }

            $this->setCommand('stop', $bot->id);

            return $this->success(['status' => 'stopped']);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function restart($id)
    {
        try {
            $bot = TelegramBot::find($id);
            if (!$bot) {
                return $this->error(trans('telegram bot not found'));
            }

            $this->setCommand('restart', $bot->id);

            return $this->success(['status' => 'running']);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $bot = TelegramBot::find($id);
            if (!$bot) {
                return $this->error(trans('telegram bot not found'));
            }

            $this->setCommand('delete', $bot->id);

            return $this->success();
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function status($id)
    {
        try {
            $bot = TelegramBot::find($id);
            if (!$bot) {
                return $this->error(trans('telegram bot not found'));
            }
            return $this->success($bot);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function updateUseTime($id)
    {
        try {
            /** @var TelegramBot $bot */
            $bot = TelegramBot::find($id);
            if (!$bot) {
                return $this->error(trans('telegram bot not found'));
            }
            if (!$expiredTime = $this->request->post('expiredTime')) {
                return $this->error(trans('expired time is required'));
            }
            $bot->expired_time = intval($expiredTime) / 1000;
            $bot->expired_at = Carbon::createFromTimestamp($bot->expired_time);
            $bot->save();
            return $this->success(['expiredTime' => $expiredTime, 'expired_at' => $bot->expired_at]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    protected function setCommand($cmd, $botId, $token = '')
    {
        $this->redis->set('robot_command', json_encode([
            'command' => $cmd,
            'botId' => $botId,
            'token' => $token,
        ]));
    }

}