<?php

namespace William\HyperfExtTelegram\Controller\Swagger\Response;

use Hyperf\Swagger\Annotation as SA;

#[SA\Schema(
    schema: "Bot",
    description: "机器人信息",
    type: "object",
)]
class Bot
{
    #[SA\Property(property: "id", description: "id", type: "integer", example: 1)]
    public int $id;

    #[SA\Property(property: "token", description: "token", type: "string", example: "12312312:asdjkhsdfgoidsf")]
    public string $token;

    #[SA\Property(property: "username", description: "用户名", type: "string", example: "my_bot", nullable: true)]
    public ?string $username = null;

    #[SA\Property(property: "nickname", description: "昵称", type: "string", example: "测试机器人", nullable: true)]
    public ?string $nickname = null;

    #[SA\Property(property: "language", description: "语言(默认 zh_CN 简体中文)", type: "string", example: "zh_CN")]
    public string $language = "zh_CN";

    #[SA\Property(property: "expired_time", description: "过期时长（秒）", type: "integer", example: 3600, nullable: true)]
    public ?int $expired_time = null;

    #[SA\Property(property: "expired_at", description: "过期时间", type: "string", format: "date-time", example: "2025-09-11T10:00:00+08:00", nullable: true)]
    public ?string $expired_at = null;

    #[SA\Property(property: "admins", description: "管理员列表", type: "string", example: "123456,654321", nullable: true)]
    public ?string $admins = null;

    #[SA\Property(property: "status", description: "状态", type: "string", example: "active")]
    public string $status = "active";
}