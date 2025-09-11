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
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('is_bot')->default('0');
            $table->string('nickname', 256)->default('');
            $table->string('username', 100)->nullable();
            $table->string('avatar', 256)->nullable();
            $table->text('bio')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->bigInteger('chat_id')->default('0');
            $table->decimal('balance', 9, 2)->default('0.00');
            $table->bigInteger('group_id')->nullable();
            $table->integer('group_notify_status')->default('0');
            $table->integer('share_balance')->default('0');
            $table->string('group_name', 120)->nullable();
            $table->bigInteger('user_id');
            $table->bigInteger('bot_id');
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
