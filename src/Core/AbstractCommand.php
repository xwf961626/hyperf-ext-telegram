<?php

namespace William\HyperfExtTelegram\Core;

use Hyperf\Contract\TranslatorInterface;

abstract class AbstractCommand implements CommandInterface
{
    public function __construct(protected TranslatorInterface $translator)
    {
        // 设置为当前上下文语言
        $translator->setLocale(LangContext::get());
    }
}