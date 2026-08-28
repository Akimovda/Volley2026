<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Настоящий numeric Telegram user_id из вебхука /start notify_<token>.
            // Раньше приходил в запросе (required), но нигде не сохранялся —
            // фундамент для будущих фич, требующих числовой id (не chat_id диалога).
            $table->string('telegram_user_id')->nullable()->index()->after('telegram_notify_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telegram_user_id');
        });
    }
};
