<?php

return [
    'mode' => env('TELEGRAM_MODE', 'pulling'),
    'dev_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'get_avatar' => null, // null|callable
    'validate_messages' => [
        'telegram token is required' => '机器人Token必填！',
        'telegram token is invalid' => '机器人Token无效！',
        'telegram token not found' => '机器人未找到！',
    ],
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
    ]
];
