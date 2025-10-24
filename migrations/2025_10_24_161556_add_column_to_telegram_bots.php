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
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->bigInteger('telegram_user_id')->nullable()->comment("所属用户ID");
            $table->jsonb('settings')->nullable()->comment("机器人设置");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn('telegram_user_id');
            $table->dropColumn('settings');
        });
    }
};
