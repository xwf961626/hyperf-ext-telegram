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
            $table->integer('template_id')->nullable()->comment('模板ID');
            $table->integer('total')->nullable()->comment('总数');
            $table->integer('success')->nullable()->comment('成功数');
            $table->integer('fail')->nullable()->comment('失败数');
            $table->timestamp('end_time')->nullable()->comment('结束时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_notice_posts', function (Blueprint $table) {
            $table->dropColumn('template_id');
            $table->dropColumn('total');
            $table->dropColumn('success');
            $table->dropColumn('fail');
            $table->dropColumn('end_time');
        });
    }
};
