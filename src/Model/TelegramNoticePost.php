<?php

declare(strict_types=1);

namespace William\HyperfExtTelegram\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 
 * @property \Carbon\Carbon $created_at 
 * @property \Carbon\Carbon $updated_at 
 * @property \Carbon\Carbon $end_time
 * @property int $notice_id
 * @property int $template_id
 * @property int $total
 * @property int $success
 * @property int $fail
 * @property string $status
 * @property string $fail_reason
 * @property array $receivers
 * @property array $bot_ids
 * @property int $to_all
 * @property string $send_type
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
        'send_type',
        'total',
        'success',
        'fail',
        'end_time',
        'template_id',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = ['id' => 'integer', 'created_at' => 'datetime', 'end_time' => 'datetime',
        'receivers' => 'json',
        'bot_ids' => 'json',
        'updated_at' => 'datetime', 'notice_id' => 'integer', 'to_all' => 'integer'];
}
