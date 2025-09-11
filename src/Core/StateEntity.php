<?php

namespace William\HyperfExtTelegram\Core;

class StateEntity
{
    public string $key;
    public string $value;

    public static function of(mixed $arr): StateEntity
    {
        $entity = new self();
        $entity->key = $arr['key'];
        $entity->value = $arr['value'];
        return $entity;
    }
}