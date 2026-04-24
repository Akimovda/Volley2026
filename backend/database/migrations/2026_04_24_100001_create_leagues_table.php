<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('direction')->default('beach'); // classic | beach
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active | archived
            $table->jsonb('config')->nullable();
            /*  config JSON:
                {
                    "default_match_format": "bo3",
                    "default_set_points": 21,
                    "auto_create_season": bool,
                    "max_teams_per_season": int
                }
            */
            $table->timestamps();

            $table->index(['organizer_id', 'status']);
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
