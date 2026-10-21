<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_courts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->foreignId('occurrence_id')->nullable()->constrained('event_occurrences')->cascadeOnDelete();
            $t->string('name', 100);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->string('channel_key', 32)->unique();
            $t->timestamps();
            $t->unique(['event_id', 'occurrence_id', 'name']);
        });
        // В PostgreSQL NULL в unique не совпадают — отдельный индекс для площадок события без тура
        DB::statement('CREATE UNIQUE INDEX event_courts_event_name_no_occ_unique ON event_courts (event_id, name) WHERE occurrence_id IS NULL');

        Schema::table('tournament_matches', function (Blueprint $t) {
            $t->foreignId('court_id')->nullable()->constrained('event_courts')->nullOnDelete();
            $t->index('court_id');
        });

        Schema::create('broadcast_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->foreignId('occurrence_id')->nullable()->constrained('event_occurrences')->cascadeOnDelete();
            $t->json('theme')->nullable();
            $t->json('splash')->nullable();
            $t->string('summary_mode', 16)->default('every_set');
            $t->unsignedSmallInteger('set_summary_seconds')->default(15);
            $t->unsignedSmallInteger('match_summary_seconds')->default(60);
            $t->string('aspect', 8)->default('16:9');
            $t->timestamps();
            $t->unique(['event_id', 'occurrence_id']);
        });
        DB::statement('CREATE UNIQUE INDEX broadcast_settings_event_no_occ_unique ON broadcast_settings (event_id) WHERE occurrence_id IS NULL');

        Schema::create('broadcast_team_styles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_team_id')->unique()->constrained('event_teams')->cascadeOnDelete();
            $t->string('color', 9)->nullable();
            $t->timestamps();
        });

        Schema::create('broadcast_court_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('court_id')->unique()->constrained('event_courts')->cascadeOnDelete();
            $t->json('stream_urls')->nullable();
            $t->string('manual_mode', 16)->nullable();
            $t->timestamp('manual_mode_at')->nullable();
            $t->foreignId('manual_match_id')->nullable()->constrained('tournament_matches')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('broadcast_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->foreignId('occurrence_id')->nullable()->constrained('event_occurrences')->cascadeOnDelete();
            $t->foreignId('court_id')->nullable()->constrained('event_courts')->cascadeOnDelete();
            $t->string('role', 16);
            $t->string('token', 64)->unique();
            $t->string('label', 100)->nullable();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });

        $this->backfillCourts();
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_links');
        Schema::dropIfExists('broadcast_court_overrides');
        Schema::dropIfExists('broadcast_team_styles');
        Schema::dropIfExists('broadcast_settings');
        Schema::table('tournament_matches', function (Blueprint $t) {
            $t->dropConstrainedForeignId('court_id');
        });
        Schema::dropIfExists('event_courts');
    }

    /** Реальный корт — «Корт N» или значение из конфига стадии/групп; служебные метки пропускаем. */
    private function backfillCourts(): void
    {
        (new \App\Services\Broadcast\CourtRegistry())->backfill();
    }
};
