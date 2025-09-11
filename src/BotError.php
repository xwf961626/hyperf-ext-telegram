<?php

namespace William\HyperfExtTelegram;

use William\HyperfExtTelegram\Core\Annotation\TelegramState;
use William\HyperfExtTelegram\Helper\Logger;
use Hyperf\Di\Annotation\AnnotationCollector;
use function Hyperf\Support\make;

class BotError
{
    const OrderNotFound = 'error_order_not_found';
    const InvalidAddress = 'error_invalid_address';
    const SystemError = 'system_error';
    const InBalance = 'in_balance';
    const GroupIdNoOwner = 'group_id_no_owner';

    public static function register()
    {
        $stateClasses = AnnotationCollector::getClassesByAnnotation(TelegramState::class);
        Logger::info("注册状态处理器 State handlers: " . json_encode($stateClasses));
        /**
         * @var  $class
         * @var TelegramState $stateAnnotation
         */
        foreach ($stateClasses as $stateClass => $stateAnnotation) {
            $state = $stateAnnotation->state;
            $handleMethod = 'handle'; // 默认方法
            Logger::info("Found state: {$state} @ {$stateClass}::{$handleMethod}");
            self::$stateHandlers[$state] = [make($stateClass), $handleMethod];
        }
    }
}