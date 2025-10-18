<?php

namespace William\HyperfExtTelegram\Controller;

use Hyperf\HttpServer\Request;
use Hyperf\HttpServer\Router\Router;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramUser;

class UsersController extends BaseController
{
    public static function registerRoutes()
    {
        Router::get('telegram/users', [self::class, 'getTelegramUsers']);
    }

    public function getTelegramUsers(Request $request)
    {
        $query = TelegramUser::with(['bot']);
        if ($userId = $request->query('user_id')) {
            $query = $query->where('user_id', $userId);
        }
        Logger::debug("搜索用户：".json_encode($request->query(), JSON_UNESCAPED_UNICODE));
        if ($username = $request->query('username')) {
            $query = $query->where('username', 'like', '%' . $username . '%');
        }
        if ($nickname = $request->query('nickname')) {
            $query = $query->where('nickname', 'like', '%' . $nickname . '%');
        }
        if ($botIds = $request->query('bot_ids')) {
            $query = $query->whereIn('bot_id', explode(',', $botIds));
        }
        $results = $query->orderBy('id', 'desc')
            ->paginate($request->query('limit', 15));
        return $this->success($results);
    }
}