<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_recharge_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->datetimes();

            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('order_no', 64)->comment('订单号，唯一');
            $table->decimal('amount', 18, 2)->comment('充值金额，保留两位小数');
            $table->decimal('actual_amount', 18, 2)->nullable()->comment('实际到账金额，单位TRX');
            $table->string('currency', 16)->default('usdt')->comment('下单币种');
            $table->json('amount_detail')->nullable()->comment('各币种充值金额详情');
            $table->enum('status', ['pending', 'success', 'failed', 'canceled', 'expired'])
                ->default('pending')->comment('订单状态');
            $table->string('tx_hash', 128)->nullable()->comment('链上交易哈希');
            $table->string('from_address', 64)->nullable()->comment('付款地址');
            $table->string('to_address', 64)->comment('收款地址');
            $table->timestamp('expired_at')->nullable()->comment('过期时间');


            $table->unique('order_no', 'unique_order_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_recharge_orders');
    }
};
