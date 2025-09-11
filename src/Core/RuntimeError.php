<?php

namespace William\HyperfExtTelegram\Core;

class RuntimeError extends \Exception
{
    protected array $extra = [];

    public function __construct(string $message = "", array $extra = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->extra = $extra;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }
}