<?php

namespace William\HyperfExtTelegram\Controller;

use Hyperf\HttpServer\Request;
use Hyperf\HttpServer\Router\Router;
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
        if ($keywords = $request->query('keywords')) {
            $query = $query->where('username', 'like', '%' . $keywords . '%')
                ->orWhere('nickname', 'like', '%' . $keywords . '%');
        }
        $results = $query->orderBy('id', 'desc')
            ->paginate($request->query('limit', 15));
        return $this->success($results);
    }
}