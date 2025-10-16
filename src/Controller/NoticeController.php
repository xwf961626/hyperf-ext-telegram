<?php

namespace William\HyperfExtTelegram\Controller;

use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\HttpServer\Request;
use Hyperf\HttpServer\Router\Router;
use William\HyperfExtTelegram\Job\PostNoticeJob;
use William\HyperfExtTelegram\Model\TelegramNotice;
use William\HyperfExtTelegram\Model\TelegramNoticePost;


class NoticeController extends BaseController
{
    private \Hyperf\AsyncQueue\Driver\DriverInterface $queue;

    public function __construct(DriverFactory $driverFactory)
    {
        parent::__construct();
        $this->queue = $driverFactory->get('default');
    }

    public static function registerRoutes()
    {
        Router::post('telegram/notices', [self::class, 'create']);
        Router::delete('telegram/notices/{id}', [self::class, 'delete']);
        Router::put('telegram/notices/{id}', [self::class, 'edit']);
        Router::get('telegram/notices', [self::class, 'getList']);

        Router::post('telegram/notices/{id}/post', [self::class, 'post']);
    }

    public function getList(Request $request)
    {
        $query = TelegramNotice::query();
        if ($keywords = $request->query('keywords')) {
            $query = $query->where('title', 'like', '%' . $keywords . '%');
        }
        $results = $query->orderBy('updated_at', 'desc')
            ->paginate($request->query('limit', 15));
        return $this->success($results);
    }

    public function create(Request $request)
    {
        if (!$title = $request->input('title')) {
            return $this->error('标题必填');
        }
        if (!$content = $request->input('content')) {
            return $this->error('内容必填');
        }
        try {
            $bot = TelegramNotice::create(compact('title', 'content'));
            return $this->success($bot);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function post($id)
    {
        if (TelegramNoticePost::where('status', 'pending')->exists()) {
            return $this->error('有发送广告任务正在进行中，暂时不能发送');
        }
        if (!$notice_id = $id) {
            return $this->error('公告ID必填');
        }
        $notice = TelegramNotice::find($id);
        if (!$notice) {
            return $this->error('公告未找到');
        }
        $to_all = $this->request->input('to_all', 0);
        $receivers = $this->request->input('receivers', []);
        if (empty($receivers) && !$to_all) {
            return $this->error('接收者必填');
        }
        try {
            $post = TelegramNoticePost::create(compact('notice_id', 'to_all', 'receivers'));
            $this->queue->push(new PostNoticeJob($post->id));
            return $this->success($post);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function delete($id)
    {
        $notice = TelegramNotice::find($id);
        if (!$notice) {
            return $this->error('此公告未找到');
        }

        try {
            $res = $notice->delete();
            return $this->success($res);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function edit($id)
    {
        $notice = TelegramNotice::find($id);
        if (!$notice) {
            return $this->error('此公告未找到');
        }

        try {
            $res = $notice->update($this->request->all());
            return $this->success($res);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}