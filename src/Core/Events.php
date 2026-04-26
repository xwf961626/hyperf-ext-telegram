<?php

namespace William\HyperfExtTelegram\Core;

class Events
{
    const string EVENT_MY_CHAT_MEMBER = 'update.my_chat_member';
    const string EVENT_CHAT_MEMBER = 'update.chat_member';
    const string EVENT_BOT_PULL_INTO_GROUP = 'bot_pull_into_group'; // 机器人被拉进群
    const string EVENT_BOT_BLOCKED = 'bot_kicked_from_group'; // Bot 被踢出群|用户拉黑 Bot（私聊）
    const string EVENT_BOT_UNBLOCKED = 'bot_unblock'; // 用户解除拉黑 Bot
    const string EVENT_USER_INVITED_TO_GROUP = 'user_invited_to_group'; // 用户被邀请加入群
    const string EVENT_USER_LEFT_GROUP= 'user_left_group'; // 用户退出群
    const string EVENT_USER_KICKED_FROM_GROUP = 'user_kicked_from_group'; // 用户被管理员踢出
    const string EVENT_USER_SET_ADMIN = 'user_set_admin'; // 用户被设置为管理员
    const string EVENT_CHAT_JOIN_REQUEST = 'chat_join_request'; // 用户申请加入群/频道
}