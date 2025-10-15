<?php

declare(strict_types=1);

namespace William\HyperfExtTelegram\Model;

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property int $is_bot
 * @property int $bot_id
 * @property int $user_id
 * @property string $avatar
 * @property string $bio
 * @property string $nickname
 * @property string $username
 * @property string $deleted_at
 * @property int $chat_id
 * @property string $balance
 * @property int $group_id
 * @property int $group_notify_status
 * @property int $share_balance
 * @property string $group_name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TelegramUser extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'telegram_users';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'username',
        'nickname',
        'user_id',
        'bot_id',
        'email',
        'level',
        'max_card_count',
        'balance',
        'avatar',
        'bio',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = ['id' => 'integer', 'is_bot' => 'integer', 'bot_id' => 'integer', 'chat_id' => 'integer', 'group_id' => 'integer', 'group_notify_status' => 'integer', 'share_balance' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class);
    }
}
