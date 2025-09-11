<?php

namespace William\HyperfExtTelegram;

use William\HyperfExtTelegram\Core\Menus;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\TranslatorInterface;

class Config
{
    public static function languages(): array
    {
        return ['en', 'zh_CN'];
    }

    public static function menus(): array
    {
        return [
            [Menus::BALANCE_QUICK_RENT, Menus::QUOTA_PACKAGE, Menus::SMART_QUOTA],
            [Menus::ENERGY_RENTAL, Menus::TRX_QUICK_EXCHANGE, Menus::ADDRESS_MONITOR],
            [Menus::REAL_TIME_U_PRICE, Menus::NOTIFY_GROUP, Menus::CONTACT_SERVICE],
            [Menus::BALANCE_RECHARGE, Menus::ENERGY_QUICK_RENT, Menus::USER_CENTER],
        ];
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