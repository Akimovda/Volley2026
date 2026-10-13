<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // NULL = раннего окна для владельцев абонементов нет (совпадает с общим стартом регистрации)
        Schema::table('events', function (Blueprint $table) {
            $table->timestampTz('subscription_registration_starts_at')->nullable();
        });
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->timestampTz('subscription_registration_starts_at')->nullable();
            // дедуп уведомления «для вас открылась запись по абонементу»
            $table->timestampTz('subscription_reg_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('subscription_registration_starts_at'));
        Schema::table('event_occurrences', fn (Blueprint $t) => $t->dropColumn(['subscription_registration_starts_at', 'subscription_reg_notified_at']));
    }
};
