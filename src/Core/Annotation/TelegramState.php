<?php

namespace William\HyperfExtTelegram\Core\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS)]
class TelegramState extends AbstractAnnotation
{
    const SELECT_QUOTA = 'select_quota_num';
    const RECHARGE_AMOUNT = 'recharge_amount';
    const CHOOSE_QUOTA = 'choose_quota';

    public function __construct(public string $state)
    {

    }
}