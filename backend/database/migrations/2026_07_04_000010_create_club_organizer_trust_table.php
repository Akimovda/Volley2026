<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_organizer_trust', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')
                ->constrained('users')->cascadeOnDelete();
            $table->string('trust_level', 20)->default('prepaid_only');
            // prepaid_only | allow_on_site | trusted
            $table->timestamps();
            $table->unique(['location_id', 'organizer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_organizer_trust');
    }
};
