<?php

namespace William\HyperfExtTelegram\Core\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_METHOD)]
class ReturnTo extends AbstractAnnotation
{
    const RETURN_TO_VIRTUAL_CARD = 'returnToVirtualCard';
    const VIRTUAL_CARD_LIST = 'virtualCardList';

    public function __construct(public string $to)
    {

    }
}