<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('tournament_seasons')->cascadeOnDelete();
            $table->string('name');              // "Hard", "Lite", "Open"
            $table->unsignedSmallInteger('level')->default(1); // 1=высший
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->unsignedSmallInteger('max_teams')->nullable();
            $table->jsonb('config')->nullable();
            /*  config JSON:
                {
                    "promote_count": int,
                    "relegate_count": int,
                    "eliminate_count": int,
                    "promote_to": "Hard",
                    "reserve_absorb": bool
                }
            */
            $table->timestamps();

            $table->index(['season_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_leagues');
    }
};
