<?php

namespace William\HyperfExtTelegram;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\TranslatorInterface;
use function Hyperf\Config\config;

class Config
{
    public static function languages(): array
    {
        return config('telegram.languages', ['zh_CN']);
    }

    public static function menus(): array
    {
        return config('telegram.menus', []);
    }

    public static function messages(): array
    {
        /** @var TranslatorInterface $translator */
        $translator = ApplicationContext::getContainer()->get(TranslatorInterface::class);
        return array_keys($translator->getLoader()->load('en', 'message'));
    }

    public static function buttons(): array
    {
        /** @var TranslatorInterface $translator */
        $translator = ApplicationContext::getContainer()->get(TranslatorInterface::class);
        return array_keys($translator->getLoader()->load('en', 'buttons'));
    }
}