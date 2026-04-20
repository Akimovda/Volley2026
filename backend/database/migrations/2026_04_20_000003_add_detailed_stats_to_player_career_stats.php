<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_career_stats', function (Blueprint $table) {
            $table->unsignedInteger('total_serves')->default(0)->after('elo_rating');
            $table->unsignedInteger('total_aces')->default(0)->after('total_serves');
            $table->unsignedInteger('total_serve_errors')->default(0)->after('total_aces');

            $table->unsignedInteger('total_attacks')->default(0)->after('total_serve_errors');
            $table->unsignedInteger('total_kills')->default(0)->after('total_attacks');
            $table->unsignedInteger('total_attack_errors')->default(0)->after('total_kills');

            $table->unsignedInteger('total_blocks')->default(0)->after('total_attack_errors');
            $table->unsignedInteger('total_block_errors')->default(0)->after('total_blocks');

            $table->unsignedInteger('total_digs')->default(0)->after('total_block_errors');
            $table->unsignedInteger('total_reception_errors')->default(0)->after('total_digs');

            $table->unsignedInteger('total_assists')->default(0)->after('total_reception_errors');
            $table->unsignedInteger('total_points_detailed')->default(0)->after('total_assists');

            $table->decimal('serve_efficiency', 5, 2)->default(0)->after('total_points_detailed');
            $table->decimal('attack_efficiency', 5, 2)->default(0)->after('serve_efficiency');
            $table->decimal('reception_efficiency', 5, 2)->default(0)->after('attack_efficiency');

            $table->unsignedSmallInteger('mvp_count')->default(0)->after('reception_efficiency');
        });
    }

    public function down(): void
    {
        Schema::table('player_career_stats', function (Blueprint $table) {
            $table->dropColumn([
                'total_serves', 'total_aces', 'total_serve_errors',
                'total_attacks', 'total_kills', 'total_attack_errors',
                'total_blocks', 'total_block_errors',
                'total_digs', 'total_reception_errors',
                'total_assists', 'total_points_detailed',
                'serve_efficiency', 'attack_efficiency', 'reception_efficiency',
                'mvp_count',
            ]);
        });
    }
};
