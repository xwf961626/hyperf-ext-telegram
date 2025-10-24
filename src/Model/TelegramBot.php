<?php

declare(strict_types=1);

namespace William\HyperfExtTelegram\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $token
 * @property string $username
 * @property string $nickname
 * @property string $language
 * @property int $expired_time
 * @property int $telegram_user_id
 * @property string $expired_at
 * @property array $admins
 * @property string $status
 * @property array $settings
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TelegramBot extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'telegram_bots';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'id',
        'token',
        'username',
        'status',
        'language',
        'expired_time',
        'expired_at',
        'telegram_user_id',
        'settings',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'id' => 'integer',
        'telegram_user_id' => 'integer',
        'settings' => 'json',
        'expired_time' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    public function user(): \Hyperf\Database\Model\Relations\BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }
}
