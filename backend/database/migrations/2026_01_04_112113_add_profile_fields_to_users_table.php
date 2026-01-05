<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('patronymic')->nullable(); // отчество (видно только админам)
            $table->string('phone')->nullable(); // +7(XXX)XXX-XX-XX
            $table->timestamp('phone_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_name',
                'first_name',
                'patronymic',
                'phone',
                'phone_verified_at',
            ]);
        });
    }
};

