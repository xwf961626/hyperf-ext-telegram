<?php

namespace William\HyperfExtTelegram\Component;

use Hyperf\Contract\TranslatorInterface;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;
use William\HyperfExtTelegram\Core\AbstractCommand;
use William\HyperfExtTelegram\Core\Annotation\Command;
use William\HyperfExtTelegram\Core\Instance;
use function Hyperf\Support\make;

#[Command(command: '/start')]
class StartCommand extends AbstractCommand
{
    public function __construct(TranslatorInterface $translator)
    {
        parent::__construct($translator);
    }

    /**
     * @throws TelegramSDKException
     */
    public function handle(Instance $instance, Update $update): void
    {
        $instance->reply(new WelcomeMessage(
            $instance->telegram,
            $instance->getChatId($update),
            $instance->getKeyboards(),
            $instance->getUsername($update),
        ));
    }
}