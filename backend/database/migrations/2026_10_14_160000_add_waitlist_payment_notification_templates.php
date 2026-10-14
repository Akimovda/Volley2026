<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $templates = [
        'waitlist_payment_required' => 'Лист ожидания: оплатите место за N минут (со ссылкой)',
        'waitlist_payment_expired'  => 'Лист ожидания: запись отменена — не оплачено вовремя',
    ];

    public function up(): void
    {
        $now = now();
        $existing = DB::table('notification_templates')->whereIn('code', array_keys($this->templates))->pluck('code')->toArray();

        foreach ($this->templates as $code => $name) {
            if (in_array($code, $existing, true)) {
                continue;
            }
            // is_active=false + пустые шаблоны: содержимое задаётся в коде
            DB::table('notification_templates')->insert([
                'code' => $code, 'channel' => null, 'name' => $name,
                'title_template' => null, 'body_template' => null,
                'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('code', array_keys($this->templates))->delete();
    }
};
