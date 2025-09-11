<?php

declare(strict_types=1);

namespace William\HyperfExtTelegram;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $token
 * @property string $username
 * @property string $nickname
 * @property int $expired_time
 * @property string $expired_at
 * @property array $admins
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Bot extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'bots';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'id',
        'token',
        'username',
        'status',
        'expired_time',
        'expired_at',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = ['id' => 'integer', 'expired_time' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
