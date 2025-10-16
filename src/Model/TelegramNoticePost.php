<?php

declare(strict_types=1);

namespace William\HyperfExtTelegram\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 
 * @property \Carbon\Carbon $created_at 
 * @property \Carbon\Carbon $updated_at 
 * @property int $notice_id 
 * @property string $status 
 * @property string $fail_reason
 * @property array $receivers
 * @property array $bot_ids
 * @property int $to_all
 */
class TelegramNoticePost extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'telegram_notice_posts';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'notice_id',
        'status',
        'receivers',
        'bot_ids',
        'to_all',
        'fail_reason',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = ['id' => 'integer', 'created_at' => 'datetime',
        'receivers' => 'json',
        'bot_ids' => 'json',
        'updated_at' => 'datetime', 'notice_id' => 'integer', 'to_all' => 'integer'];
}
