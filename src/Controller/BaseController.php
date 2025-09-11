<?php

namespace William\HyperfExtTelegram\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

class BaseController
{
    #[Inject]
    protected ResponseInterface $response;
    #[Inject]
    protected RequestInterface $request;

    protected function error($message, $code = 500)
    {
        return $this->response
            ->withStatus($code) // 设置状态码
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream(json_encode(['code' => $code, 'msg' => $message])));
    }

    protected function success($data)
    {
        return $this->response
            ->withStatus(200) // 设置状态码
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream(json_encode(['code' => 200, 'data' => $data])));
    }
}