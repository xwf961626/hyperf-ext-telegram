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
 * @property string $expired_at
 * @property array $admins
 * @property string $status
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
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = ['id' => 'integer', 'expired_time' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
