<?php

use function Hyperf\Support\env;

return [
    'welcome' => \William\HyperfExtTelegram\Component\WelcomeMessage::class,
    'languages' => ['zh_CN', 'en'],
    'mode' => env('TELEGRAM_MODE', 'pulling'),
    'dev_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'get_avatar' => null, // null|callable
    'validate_messages' => [
        'telegram token is required' => '机器人Token必填！',
        'telegram token is invalid' => '机器人Token无效！',
        'telegram token not found' => '机器人未找到！',
    ],
    'store_dir' => env('TELEGRAM_STORE_DIR', 'runtime/bot'),
    'commands' => [
        [
            'command' => 'start',
            'description' => '开始使用'
        ],
        [
            'command' => 'language',
            'description' => '切换语言'
        ],
        [
            'command' => 'help',
            'description' => '获取帮助'
        ]
    ],
    'command_handlers' => [
        'start' => \William\HyperfExtTelegram\Component\StartCommand::class,
        'language' => \William\HyperfExtTelegram\Component\Language\LanguageCommand::class,
    ],
    'callback_handlers' => [
        'switch_language' => \William\HyperfExtTelegram\Component\Language\SwitchLanguage::class,
    ],
    'filter' => null, // class implements \William\HyperfExtTelegram\Core\UpdateFilterInterface
];
