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
        Schema::table('telegram_notices', function (Blueprint $table) {
            $table->json('buttons')->nullable()->comment('自定义按钮设置');
            $table->string('attach')->nullable()->comment('图片或视频');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_notices', function (Blueprint $table) {
            //
        });
    }
};
