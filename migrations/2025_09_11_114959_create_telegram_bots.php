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
        Schema::create('telegram_bots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('token', 255)->comment('令牌');
            $table->string('username', 100)->comment('用户名')->nullable();
            $table->string('nickname', 100)->nullable()->comment('昵称');
            $table->string('language', 10)->default('zh_CN')->comment('语言: 默认zh_CN简体中文');
            $table->integer('expired_time')->nullable()->comment('过期时长（秒）');
            $table->timestamp('expired_at')->nullable()->comment('过期时间');
            $table->string('admins')->nullable()->comment('管理员列表');
            $table->string('status')->default('active')->comment('状态');
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
