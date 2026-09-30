<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $codes = [
        'organizer_pro_payment_pending' => 'Организатор Pro: оплата отмечена, ожидает подтверждения (админу)',
        'organizer_pro_paid_activated'  => 'Организатор Pro активирован после подтверждения оплаты',
    ];

    public function up(): void
    {
        $now = now();
        $existing = DB::table('notification_templates')->whereIn('code', array_keys($this->codes))->pluck('code')->all();
        $rows = [];
        foreach ($this->codes as $code => $name) {
            if (in_array($code, $existing, true)) {
                continue;
            }
            $rows[] = [
                'code' => $code, 'channel' => null, 'name' => $name,
                'title_template' => null, 'body_template' => null, 'is_active' => false,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        if ($rows) {
            DB::table('notification_templates')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('code', array_keys($this->codes))->delete();
    }
};
