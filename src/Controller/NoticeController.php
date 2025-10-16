<?php

namespace William\HyperfExtTelegram\Controller;

use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
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
        $this->queue = $driverFactory->get('fast');
    }

    public static function registerRoutes()
    {
        Router::post('telegram/notices', [self::class, 'create']);
        Router::delete('telegram/notices/{id}', [self::class, 'delete']);
        Router::put('telegram/notices/{id}', [self::class, 'edit']);
        Router::get('telegram/notices', [self::class, 'getList']);

        Router::post('telegram/notices/{id}/post', [self::class, 'post']);

        Router::post('telegram/upload', [self::class, 'upload']);
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
        $bot_ids = $this->request->input('bot_ids', []);
        $receivers = $this->request->input('receivers', []);
        if (empty($receivers) && !$to_all) {
            return $this->error('接收者必填');
        }
        try {
            $post = TelegramNoticePost::create(compact('notice_id', 'to_all', 'bot_ids', 'receivers'));
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

    public function upload(RequestInterface $request, ResponseInterface $response)
    {
        $uploadedFile = $request->getUploadedFiles()['file'] ?? null;

        if (!$uploadedFile) {
            return $response->json([
                'code' => 400,
                'message' => 'No file uploaded.',
            ]);
        }

        // 检查上传是否成功
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return $response->json([
                'code' => 400,
                'message' => 'File upload error.',
            ]);
        }

        $uploadDir = BASE_PATH . '/storage/bot';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // 原始文件名和扩展名
        $originalName = $uploadedFile->getClientFilename();
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);

        // 使用 uniqid() 生成一个唯一的随机文件名
        $newFileName = uniqid('file_', true) . '.' . $extension;
        $filePath = $uploadDir . '/' . $newFileName;

        // 移动文件
        $uploadedFile->moveTo($filePath);

        // 获取文件类型
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        return $response->json([
            'code' => 200,
            'message' => '上传成功',
            'data' => [
                'name' => $originalName,
                'path' => 'storage/bot/' . $newFileName, // 相对路径
                'absolute_path' => $filePath,                // 绝对路径
                'mime' => $mimeType,                         // 文件类型
            ],
        ]);
    }
}