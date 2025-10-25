<?php

declare(strict_types=1);

namespace William\HyperfExtTelegram\Middleware;

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
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getHeaderLine('CLONE-KEY') ?? '';
        if ($token == "") {
            throw new \RuntimeException('Missing CLONE-KEY', 400);
        }
        if ($token == env('CLONE_KEY')) {
            return $handler->handle($request);
        }

        throw new TokenValidException('CLONE-KEY authentication does not pass', 400);
    }
}
