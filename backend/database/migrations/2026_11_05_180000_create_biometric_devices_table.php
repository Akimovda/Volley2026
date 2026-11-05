<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Face ID / Touch ID: токен на каждое устройство (раньше — один users.biometric_token на пользователя,
 * второе устройство перезаписывало первое) и в базе только sha256-хеш токена (раньше — открытым текстом).
 * Существующие токены переносятся хешами, старая колонка очищается.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('biometric_devices')) {
            Schema::create('biometric_devices', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('token_hash', 64)->unique();
                $t->string('label', 120)->nullable();
                $t->timestamp('last_used_at')->nullable();
                $t->timestamps();
            });
        }

        $now = now();
        DB::table('users')->whereNotNull('biometric_token')->where('biometric_token', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($now) {
                $insert = [];
                foreach ($rows as $r) {
                    $insert[] = [
                        'user_id'    => $r->id,
                        'token_hash' => hash('sha256', (string) $r->biometric_token),
                        'label'      => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('biometric_devices')->insertOrIgnore($insert);
            });

        DB::table('users')->whereNotNull('biometric_token')->update(['biometric_token' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
