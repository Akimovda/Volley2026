<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODE = 'subscription_auto_booking_hint';

    public function up(): void
    {
        if (DB::table('notification_templates')->where('code', self::CODE)->exists()) {
            return;
        }
        // is_active=false + пустые тексты: содержание задаётся в коде (только видимость в админке)
        DB::table('notification_templates')->insert([
            'code' => self::CODE, 'channel' => null,
            'name' => 'Абонемент: настройте автозапись (выберите позицию)',
            'title_template' => null, 'body_template' => null,
            'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('notification_templates')->where('code', self::CODE)
            ->where('is_active', false)->whereNull('title_template')->delete();
    }
};
