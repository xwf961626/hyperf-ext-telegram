<?php

namespace William\HyperfExtTelegram\Trait;

use William\HyperfExtTelegram\Core\StateEntity;

trait StateTrait
{
    protected function setState(string $key, mixed $value = '', int $expiresIn = 20): void
    {
        $this->telegramInstance->setState(
            $this->telegramInstance->getChatId($this->telegramUpdate),
            $key,
            $value,
            $expiresIn,
        );
    }

    /**
     * @throws \RedisException
     */
    protected function endState(): void
    {
        $this->telegramInstance->endState($this->telegramUpdate);
    }

    protected function getState(int $chatId): ?StateEntity
    {
        return $this->telegramInstance->getState($chatId);
    }
}