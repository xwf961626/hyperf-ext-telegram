<?php

namespace William\HyperfExtTelegram\Core\Annotation;

use William\HyperfExtTelegram\Core\ErrorInterface;
use William\HyperfExtTelegram\Helper\Logger;
use Hyperf\Di\Annotation\AnnotationCollector;
use function Hyperf\Support\make;

class AnnotationRegistry
{
    protected static array $commandHandlers = [];
    protected static array $queryCallbackHandlers = [];

    protected static array $menuHandlers = [];
    protected static array $stateHandlers = [];
    protected static array $eventHandlers = [];

    protected static array $returnHandlers = [];
    protected static array $errorHandlers = [];


    public static function register(): void
    {
        self::registerStateHandlers();
        self::registerCommandHandlers();
        self::registerQueryCallbackHandlers();
        self::registerMenuHandlers();
        self::registerEventHandlers();
        self::registerReturnToHandlers();
        self::registerErrorHandlers();
    }

    public static function getCommandHandler(string $command): ?array
    {
        return self::$commandHandlers[$command] ?? null;
    }

    public static function getQueryCallbackHandler(string $route): ?array
    {
        return self::$queryCallbackHandlers[trim($route, '/')] ?? null;
    }

    public static function getMenuHandler(string $route): ?array
    {
        return self::$menuHandlers[$route] ?? null;
    }

    public static function getStateHandler(string $route): ?array
    {
        return self::$stateHandlers[$route] ?? null;
    }

    public static function getEventHandler(string $route): ?array
    {
        return self::$eventHandlers[$route] ?? null;
    }

    public static function getReturnHandler(string $route): ?array
    {
        return self::$returnHandlers[$route] ?? null;
    }

    private static function registerStateHandlers(): void
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

    private static function registerCommandHandlers(): void
    {
        $classes = AnnotationCollector::getClassesByAnnotation(Command::class);
        foreach ($classes as $class => $annotation) {
            /** @var Command $annotation */
            $command = $annotation->command;
            $method = 'handle'; // 默认方法
            Logger::info("Found command: {$command} @ {$class}::{$method}");
            self::$commandHandlers[$command] = [make($class), $method];
        }
    }

    private static function registerQueryCallbackHandlers(): void
    {
        $classesQ = AnnotationCollector::getClassesByAnnotation(QueryCallback::class);
        foreach ($classesQ as $class => $annotation) {
            /** @var QueryCallback $annotation */
            $path = trim($annotation->path, '/');
            $method = 'handle'; // 默认方法
            Logger::info("Found query callback: {$path} @ {$class}::{$method}");
            self::$queryCallbackHandlers[$path] = [$class, $method];
        }
    }

    private static function registerMenuHandlers(): void
    {

        $classesM = AnnotationCollector::getClassesByAnnotation(Menu::class);
        foreach ($classesM as $class => $annotation) {
            /** @var Menu $annotation */
            $menu = $annotation->menu;
            $method = 'handle'; // 默认方法
            Logger::info("Found menu: {$menu} @ {$class}::{$method}");
            self::$menuHandlers[$menu] = [$class, $method];
        }
    }

    private static function registerEventHandlers(): void
    {
        $classesE = AnnotationCollector::getClassesByAnnotation(\William\HyperfExtTelegram\Core\Annotation\Event::class);
        foreach ($classesE as $class => $annotation) {
            /** @var \William\HyperfExtTelegram\Core\Annotation\Event $annotation */
            $event = $annotation->event;
            $method = 'handle'; // 默认方法
            Logger::info("Found event: {$event} @ {$class}::{$method}");
            self::$eventHandlers[$event] = [$class, $method];
        }
    }

    private static function registerReturnToHandlers(): void
    {
        $returnMethods = AnnotationCollector::getMethodsByAnnotation(ReturnTo::class);
        foreach ($returnMethods as $method) {
            /** @var ReturnTo $annotation */
            $annotation = $method['annotation'];
            $to = $annotation->to;
            Logger::info("Found return to: $to  {$method['class']}::{$method['method']}");
//            [$class, $method] = explode('::', $methodString);
            self::$returnHandlers[$to] = [$method['class'], $method['method']];
        }
        Logger::info('注册返回处理器：' . json_encode(self::$returnHandlers));
    }

    private static function registerErrorHandlers(): void
    {
        $classes = AnnotationCollector::getClassesByAnnotation(Error::class);
        foreach ($classes as $class => $annotation) {
            /** @var Error $annotation */
            $err = $annotation->error;
            $method = 'handle'; // 默认方法
            Logger::info("Found error handler: {$err} @ {$class}::{$method}");
            self::$errorHandlers[$err] = [make($class), $method];
        }
    }

    public static function getErrorHandler($err): ?ErrorInterface
    {
        if (isset(self::$errorHandlers[$err])) {
            [$instance, $method] = self::$errorHandlers[$err];
            return $instance;
        }
        return null;
    }
}