<?php

namespace William\HyperfExtTelegram\Core\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS)]
class QueryCallback extends AbstractAnnotation
{
    const PAY_ADDRESSES = 'payAddresses';

    public function __construct(public string $path)
    {

    }
}