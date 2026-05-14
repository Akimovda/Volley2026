<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followed_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_user_id', 'followed_user_id']);
            $table->index('followed_user_id');
        });

        Schema::table('premium_subscriptions', function (Blueprint $table) {
            $table->boolean('hide_from_followers')->default(false)->after('notify_city_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_follows');
        Schema::table('premium_subscriptions', function (Blueprint $table) {
            $table->dropColumn('hide_from_followers');
        });
    }
};
