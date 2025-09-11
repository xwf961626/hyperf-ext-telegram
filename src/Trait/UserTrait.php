<?php

namespace William\HyperfExtTelegram\Trait;

use App\Model\User;

trait UserTrait
{
    protected function getUsername(): string
    {
        return $this->telegramInstance->getUsername($this->telegramUpdate) ?: '';
    }

    protected function getNickname(): string
    {
        return $this->telegramInstance->getNickname($this->telegramUpdate) ?: '';
    }

    protected function getBalance(): float
    {
        return (float)$this->telegramInstance->getCurrentUser()->balance;
    }

    protected function getUser(): User
    {
        return $this->telegramInstance->getCurrentUser();
    }
}