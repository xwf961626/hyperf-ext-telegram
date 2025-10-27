<?php

declare(strict_types=1);

namespace William\HyperfExtTelegram\Middleware;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Phper666\JWTAuth\Exception\JWTException;
use Phper666\JWTAuth\Exception\TokenValidException;
use Phper666\JWTAuth\Util\JWTUtil;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function Hyperf\Support\env;

class CloneMiddleware implements MiddlewareInterface
{
    protected ContainerInterface $container;

    protected RequestInterface $request;

    protected HttpResponse $response;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getHeaderLine('CLONE-KEY') ?? '';
        if ($token == "") {
            return $this->response->json([
                'code' => 400,
                'msg' => 'Missing CLONE-KEY',
                'flag' => false,
            ])->withStatus(400);
        }
        if ($token == env('CLONE_KEY')) {
            return $handler->handle($request);
        }

        return $this->response->json([
            'code' => 400,
            'msg' => 'Invalid CLONE-KEY',
            'flag' => false,
        ])->withStatus(400);
    }
}
