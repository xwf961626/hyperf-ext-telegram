<?php

namespace William\HyperfExtTelegram\Clone;

class CloneFactory
{
    private static ?CloneInterface $cloner = null;

    private function __construct(Cloner $cloner)
    {
    }

    public static function get(Cloner $cloner): CloneInterface
    {
        if (!self::$cloner) {
            self::$cloner = $cloner;
        }
        return self::$cloner;
    }


}