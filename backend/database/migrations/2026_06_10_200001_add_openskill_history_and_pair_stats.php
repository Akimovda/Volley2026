<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // История рейтинга (для графика динамики)
        Schema::create('player_rating_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('tournament_matches')->nullOnDelete();
            $table->decimal('mu_before',    6, 3);
            $table->decimal('mu_after',     6, 3);
            $table->decimal('sigma_before', 6, 3);
            $table->decimal('sigma_after',  6, 3);
            $table->decimal('mu_delta',    6, 3)->storedAs('mu_after - mu_before');
            $table->decimal('sigma_delta', 6, 3)->storedAs('sigma_after - sigma_before');
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'recorded_at'], 'idx_rating_history_user');
        });

        // Статистика пар (играли вместе)
        Schema::create('player_pair_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('player1_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player2_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('matches_together')->default(0);
            $table->unsignedInteger('wins_together')->default(0);
            $table->timestamps();

            $table->unique(['player1_id', 'player2_id']);
        });

        // Статистика соперников
        Schema::create('player_opponent_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('matches_against')->default(0);
            $table->unsignedInteger('wins_against')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'opponent_id']);
        });

        // Новые колонки в player_career_stats
        Schema::table('player_career_stats', function (Blueprint $table) {
            $table->decimal('mu_peak', 6, 3)->default(25.0)->after('sigma');
            $table->date('mu_peak_date')->nullable()->after('mu_peak');
            $table->unsignedInteger('unique_opponents')->default(0)->after('mu_peak_date');
            $table->unsignedInteger('unique_partners')->default(0)->after('unique_opponents');
            $table->foreignId('main_partner_id')->nullable()->constrained('users')->nullOnDelete()->after('unique_partners');
            $table->unsignedSmallInteger('main_partner_games')->default(0)->after('main_partner_id');
            $table->decimal('pair_stability', 5, 2)->default(0)->after('main_partner_games');
            $table->string('last_5_form', 10)->nullable()->after('pair_stability');
            $table->string('last_10_form', 20)->nullable()->after('last_5_form');
            $table->decimal('points_ratio', 5, 3)->default(1.0)->after('last_10_form');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_opponent_stats');
        Schema::dropIfExists('player_pair_stats');
        Schema::dropIfExists('player_rating_history');

        Schema::table('player_career_stats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('main_partner_id');
            $table->dropColumn([
                'mu_peak', 'mu_peak_date', 'unique_opponents', 'unique_partners',
                'main_partner_games', 'pair_stability',
                'last_5_form', 'last_10_form', 'points_ratio',
            ]);
        });
    }
};
