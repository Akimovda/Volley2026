<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подтип атаки для очков «атака» (action_type = kill): spike (удар) | shot (накат) | dump (скидка). Необязательное поле.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('match_rally_events', 'attack_type')) {
            Schema::table('match_rally_events', function (Blueprint $table) {
                $table->string('attack_type', 10)->nullable()->after('assist_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('match_rally_events', 'attack_type')) {
            Schema::table('match_rally_events', function (Blueprint $table) {
                $table->dropColumn('attack_type');
            });
        }
    }
};
