<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('direction')->default('classic'); // classic | beach
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status')->default('draft'); // draft | active | completed
            $table->jsonb('config')->nullable();
            /*  config JSON:
                {
                    "auto_promotion": bool,
                    "promotion_rules": {...},
                    "reserve_carry_over": bool,
                    "reserve_priority": "fifo_plus_relegated",
                    "subscription_enabled": bool,
                    "monthly_summary": bool
                }
            */
            $table->timestamps();

            $table->index(['organizer_id', 'status']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_seasons');
    }
};
