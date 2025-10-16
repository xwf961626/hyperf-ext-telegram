<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('telegram_notice_posts', function (Blueprint $table) {
            $table->json('bot_ids')->nullable()->comment('机器人ID数组');
            $table->text('fail_reason')->nullable()->comment('发送失败的原因');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_notices', function (Blueprint $table) {
            $table->dropColumn('bot_ids');
            $table->dropColumn('fail_reason');
        });
    }
};
