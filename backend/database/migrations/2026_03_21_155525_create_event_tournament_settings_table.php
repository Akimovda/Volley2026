<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_tournament_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('registration_mode', 32)->default('individual');
            // individual | team_classic | team_beach

            $table->unsignedSmallInteger('team_size_min')->nullable();
            $table->unsignedSmallInteger('team_size_max')->nullable();

            $table->boolean('require_libero')->default(false);
            $table->unsignedInteger('max_rating_sum')->nullable();

            $table->boolean('allow_reserves')->default(false);
            $table->boolean('captain_confirms_members')->default(true);
            $table->boolean('auto_submit_when_ready')->default(false);

            $table->string('seeding_mode', 32)->nullable();
            // manual | random | rating

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['event_id']);
            $table->index(['registration_mode']);
            $table->index(['seeding_mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tournament_settings');
    }
};
