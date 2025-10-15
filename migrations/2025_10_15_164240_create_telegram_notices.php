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
        Schema::create('telegram_notices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->datetimes();
            $table->string('title')->comment('标题');
            $table->text('content')->comment('内容');
        });

        Schema::create('telegram_notice_posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->datetimes();
            $table->integer('notice_id')->comment('公告ID');
            $table->string('status', 10)->default('pending')->comment('pending:发送中，complete:完成, fail:失败');
            $table->json('receivers')->nullable()->comment('接受者ID');
            $table->boolean('to_all')->default(false)->comment('是否发给全部人');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_notices');
    }
};
