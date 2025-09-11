<?php

namespace William\HyperfExtTelegram\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Request;
use Hyperf\HttpServer\Router\Router;
use Illuminate\Support\Facades\Log;
use William\HyperfExtTelegram\Core\BotManager;
use William\HyperfExtTelegram\Model\TelegramBot;
use function Hyperf\Config\config;

class AdminController
{
    #[Inject]
    protected ResponseInterface $response;

    public function __construct(protected BotManager $botManager)
    {
    }

    public static function addRoutes(): void
    {
        Router::post('/admin/telegram/bots', [self::class, 'addTelegramBot']);
        Router::delete('/admin/telegram/bots', [self::class, 'deleteTelegramBot']);
        Router::put('/admin/telegram/bots', [self::class, 'editTelegramBot']);
        Router::get('/admin/telegram/bots', [self::class, 'getTelegramBots']);
    }

    public function addTelegramBot(Request $request)
    {
        if (!$token = $request->post('token')) {
            return $this->error(config('telegram.validate_messages')['telegram token is required']);
        }
        try {
            $bot = $this->botManager->addBot($token);
            return $this->success($bot);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->error(config('telegram.validate_messages')['telegram token is invalid']);
        }
    }

    public function getTelegramBots(Request $request)
    {
        $results = TelegramBot::query()->orderBy('id', 'desc')
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
            $bot->update($request->post());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
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
            $bot->delete();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->error($e->getMessage());
        }
        return $this->success(true);
    }

    protected function error($message, $code = 500)
    {
        return $this->response
            ->withStatus($code) // 设置状态码
            ->withHeader('Content-Type', 'text/plain')
            ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream($message));
    }

    protected function success($data)
    {
        return $this->response
            ->withStatus(200) // 设置状态码
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream(json_encode($data)));
    }
}