<?php

declare(strict_types=1);

namespace William\HyperfExtTelegram\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $order_no
 * @property string $amount
 * @property string $actual_amount
 * @property string $currency
 * @property array $amount_detail
 * @property string $status
 * @property string $tx_hash
 * @property string $from_address
 * @property string $to_address
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $expired_at
 */
class UserRechargeOrder extends Model
{
    const PENDING = 'pending';
    const SUCCESS = 'success';
    const CANCELED = 'canceled';
    const EXPIRED = 'expired';
    public bool $timestamps = true;
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'user_recharge_orders';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'user_id',
        'order_no',
        'amount',
        'currency',
        'amount_detail',
        'status',
        'tx_hash',
        'from_address',
        'to_address',
        'expired_at',
        'tx_id',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = ['id' => 'integer',
        'amount_detail' => 'json',
        'user_id' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
