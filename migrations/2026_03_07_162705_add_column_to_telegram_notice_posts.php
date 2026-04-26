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
        Schema::table('telegram_notice_posts', function (Blueprint $table) {
            $table->string('send_type', 20)->index()->comment('发送类型，如：start 发送开始文案');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_notice_posts', function (Blueprint $table) {
            $table->dropColumn('send_type');
        });
    }
};
