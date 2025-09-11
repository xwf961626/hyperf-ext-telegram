<?php

namespace William\HyperfExtTelegram\Core;

use William\HyperfExtTelegram\Core\Annotation\AnnotationRegistry;
use Hyperf\Contract\ContainerInterface;

class ErrorHandlerFactory
{
    public function __construct(protected ContainerInterface $container)
    {

    }

    public function get($err): ?ErrorInterface
    {
        return AnnotationRegistry::getErrorHandler($err);
    }
}