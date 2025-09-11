<?php

namespace William\HyperfExtTelegram\Trait;


use William\HyperfExtTelegram\Model\TelegramUser;

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

    protected function getUser(): TelegramUser
    {
        return $this->telegramInstance->getCurrentUser();
    }
}