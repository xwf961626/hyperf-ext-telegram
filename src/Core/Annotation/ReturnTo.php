<?php

namespace William\HyperfExtTelegram\Core\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_METHOD)]
class ReturnTo extends AbstractAnnotation
{
    public function __construct(public string $to)
    {

    }
}