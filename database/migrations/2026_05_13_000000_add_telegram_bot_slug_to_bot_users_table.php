<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->string('telegram_bot_slug', 64)->default('default')->after('platform');
        });

        Schema::table('bot_users', function (Blueprint $table) {
            $table->unique(['chat_id', 'platform', 'telegram_bot_slug'], 'bot_users_chat_platform_telegram_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropUnique('bot_users_chat_platform_telegram_slug_unique');
            $table->dropColumn('telegram_bot_slug');
        });
    }
};
