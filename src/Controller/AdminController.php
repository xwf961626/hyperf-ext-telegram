<?php

namespace William\HyperfExtTelegram\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Request;
use Hyperf\HttpServer\Router\Router;
use Hyperf\Redis\RedisFactory;
use Illuminate\Support\Facades\Log;
use Swoole\Table;
use William\HyperfExtTelegram\Core\BotManager;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramBot;
use William\HyperfExtTelegram\Process\TelegramProcess;
use function Hyperf\Config\config;
use Hyperf\Swagger\Annotation as SA;

#[SA\HyperfServer(name: 'http')]
class AdminController extends BaseController
{

    protected \Hyperf\Redis\Redis $redis;

    public function __construct(protected BotManager $botManager, RedisFactory $redisFactory)
    {
        $this->redis = $redisFactory->get('default');
    }

    public static function addRoutes(): void
    {
        Router::post('/admin/telegram/bots', [self::class, 'addTelegramBot']);
        Router::delete('/admin/telegram/bots/{id}', [self::class, 'deleteTelegramBot']);
        Router::put('/admin/telegram/bots/{id}', [self::class, 'editTelegramBot']);
        Router::get('/admin/telegram/bots', [self::class, 'getTelegramBots']);
    }

    #[SA\Post(path: '/admin/telegram/bots', summary: '添加机器人接口', tags: ['机器人管理'])]
    #[SA\RequestBody(
        description: '请求参数',
        content: [
            new SA\MediaType(
                mediaType: 'application/json',
                schema: new SA\Schema(
                    required: ['token'],
                    properties: [
                        new SA\Property(property: 'token', description: 'token', type: 'string'),
                        new SA\Property(property: 'username', description: '用户名', type: 'string'),
                        new SA\Property(property: 'nickname', description: '昵称', type: 'string'),
                        new SA\Property(property: 'language', description: '默认语言', type: 'string'),
                    ]
                ),
            ),
        ],
    )]
    #[SA\Response(
        response: 200,
        description: '返回值的描述',
        content: [
            new SA\MediaType(
                mediaType: 'application/json',
                schema: new SA\Schema(
                    properties: [
                        new SA\Property(property: 'code', description: 'code', type: 'integer'),
                        new SA\Property(
                            property: 'data',
                            ref: "#/components/schemas/Bot",
                            description: 'data',
                            type: 'object'
                        ),
                    ]
                ),
            ),
        ],
    )]
    public function addTelegramBot(Request $request, Table $table)
    {
        if (!$token = $request->post('token')) {
            return $this->error(config('telegram.validate_messages')['telegram token is required']);
        }
        $language = $request->post('language', 'zh_CN');
        try {
            $bot = TelegramBot::updateOrCreate(['token' => $token], ['language' => $language, 'status' => 'stopped']);
            $table->set('robot_command', ['command' => 'add', 'data' => ['botId' => $bot->id]]);
            return $this->success($bot);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->error(config('telegram.validate_messages')['telegram token is invalid']);
        }
    }

    #[SA\Get(path: '/admin/telegram/bots', summary: '分页查询机器人', tags: ['机器人管理'])]
    #[SA\QueryParameter(name: 'limit', description: '每页数量，默认15', required: false, schema: new SA\Schema(type: 'integer'))]
    #[SA\QueryParameter(name: 'page', description: '页码，默认1', required: false, schema: new SA\Schema(type: 'integer'))]
    #[SA\QueryParameter(name: 'keywords', description: '关键词查询', required: false, schema: new SA\Schema(type: 'string'))]
    #[SA\Response(
        response: 200,
        description: '返回值的描述',
        content: [
            new SA\MediaType(
                mediaType: 'application/json',
                schema: new SA\Schema(
                    properties: [
                        new SA\Property(property: 'code', description: 'code', type: 'integer'),
                        new SA\Property(
                            property: 'data',
                            description: 'data',
                            type: 'array',
                            items: new SA\Items(ref: "#/components/schemas/Bot")
                        ),
                    ]
                ),
            ),
        ],
    )]
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

    #[SA\Put(path: '/admin/telegram/bots/{id}', summary: '修改机器人接口', tags: ['机器人管理'])]
    #[SA\RequestBody(
        description: '请求参数',
        content: [
            new SA\MediaType(
                mediaType: 'application/json',
                schema: new SA\Schema(ref: "#/components/schemas/Bot"),
            ),
        ],
    )]
    #[SA\Response(
        response: 200,
        description: '返回值的描述',
        content: [
            new SA\MediaType(
                mediaType: 'application/json',
                schema: new SA\Schema(
                    properties: [
                        new SA\Property(property: 'code', description: 'code', type: 'integer'),
                        new SA\Property(
                            property: 'data',
                            ref: "#/components/schemas/Bot",
                            description: 'data',
                            type: 'object'
                        ),
                    ]
                ),
            ),
        ],
    )]
    public function editTelegramBot(int $id, Request $request, Table $table)
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
                $table->set('robot_command', ['command' => 'stop', 'data' => ['botId' => $bot->id]]);
            }
            if ($oldStatus === 'stopped' && $bot->status === 'running') {
                Logger::info("管理员开启机器人");
                $table->set('robot_command', ['command' => 'start', 'data' => ['botId' => $bot->id]]);
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->error($e->getMessage());
        }
        return $this->success($bot);
    }

    public function deleteTelegramBot(int $id, Request $request, Table $table)
    {
        $bot = TelegramBot::find($id);
        if (!$bot) {
            return $this->error(config('telegram.validate_messages')['telegram token not found']);
        }
        try {
            $bot->delete();
            $table->set('robot_command', ['command' => 'stop', 'data' => ['botId' => $bot->id]]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->error($e->getMessage());
        }
        return $this->success(true);
    }

}