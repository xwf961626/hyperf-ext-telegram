<?php

declare(strict_types=1);

namespace William\HyperfExtTelegram\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 
 * @property \Carbon\Carbon $created_at 
 * @property \Carbon\Carbon $updated_at 
 * @property string $title 
 * @property string $attach
 * @property array $buttons
 * @property string $content
 */
class TelegramNotice extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'telegram_notices';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'title',
        'content',
        'attach',
        'buttons'
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = ['id' => 'integer', 'buttons' => 'json', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
