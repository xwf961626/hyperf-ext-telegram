<?php

namespace William\HyperfExtTelegram\Core\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS)]
class QueryCallback extends AbstractAnnotation
{
    public function __construct(public string $path)
    {

    }
}