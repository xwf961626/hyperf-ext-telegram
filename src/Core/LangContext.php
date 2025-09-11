<?php

namespace William\HyperfExtTelegram\Core;

use Hyperf\Context\Context;

class LangContext
{
    public const LOCALE_KEY = 'locale';

    public static function set(string $locale): void
    {
        Context::set(self::LOCALE_KEY, $locale);
    }

    public static function get(): string
    {
        return Context::get(self::LOCALE_KEY, 'en'); // 默认英文
    }

    public static function has(): bool
    {
        return Context::has(self::LOCALE_KEY);
    }

    public static function clear(): void
    {
        Context::set(self::LOCALE_KEY, null);
    }
}
