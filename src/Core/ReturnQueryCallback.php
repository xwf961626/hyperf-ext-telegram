<?php

namespace William\HyperfExtTelegram\Core;

use Hyperf\Di\Annotation\Inject;
use William\HyperfExtTelegram\Core\Annotation\AnnotationRegistry;
use William\HyperfExtTelegram\Core\Annotation\QueryCallback;
use function Hyperf\Support\make;

#[QueryCallback(path: 'return')]
class ReturnQueryCallback extends AbstractQueryCallback
{
    function _handle(): void
    {
        $to = $this->getQueryParam('to');
        if ([$class, $method] = AnnotationRegistry::getReturnHandler($to)) {
            $i = make($class);
            call_user_func([$i, $method], $this->telegramInstance, $this->telegramUpdate);
        }
    }
}