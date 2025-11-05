<?php

namespace William\HyperfExtTelegram\Core\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS)]
class Listener extends AbstractAnnotation
{
    public function __construct(public string $event)
    {

    }
}