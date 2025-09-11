<?php

namespace William\HyperfExtTelegram\Core;

interface MessageDecorator
{
    public function decorate(MessageBuilder $messageBuilder): void;
}