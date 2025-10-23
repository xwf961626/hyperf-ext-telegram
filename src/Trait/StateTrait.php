<?php

namespace William\HyperfExtTelegram\Trait;

use William\HyperfExtTelegram\Core\StateBus;

trait StateTrait
{
    protected function startState(string $key, int $ttl): StateBus
    {
        return $this->telegramInstance->startState(
            $this->telegramInstance->getChatId($this->telegramUpdate),
            $key,
            $ttl,
        );
    }

    protected function setState(string $key, mixed $value = ''): void
    {
        $this->telegramInstance->setState(
            $this->telegramInstance->getChatId($this->telegramUpdate),
            $key,
            $value,
        );
    }

    /**
     * @throws \RedisException
     */
    protected function endState(): void
    {
        $this->telegramInstance->endState($this->telegramUpdate);
    }

    protected function getState(int $chatId): ?StateBus
    {
        return $this->telegramInstance->getState($chatId);
    }
}