<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $existing = DB::table('notification_templates')
            ->whereIn('code', ['cash_payment_reminder', 'cash_payment_confirmed'])
            ->pluck('code')
            ->toArray();

        $toInsert = [];

        if (!in_array('cash_payment_reminder', $existing, true)) {
            $toInsert[] = [
                'code'           => 'cash_payment_reminder',
                'channel'        => null,
                'name'           => 'Напоминание об оплате наличными (не подтверждена организатором)',
                'title_template' => null,
                'body_template'  => null,
                'is_active'      => false,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if (!in_array('cash_payment_confirmed', $existing, true)) {
            $toInsert[] = [
                'code'           => 'cash_payment_confirmed',
                'channel'        => null,
                'name'           => 'Оплата наличными подтверждена организатором',
                'title_template' => null,
                'body_template'  => null,
                'is_active'      => false,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if ($toInsert) {
            DB::table('notification_templates')->insert($toInsert);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereIn('code', ['cash_payment_reminder', 'cash_payment_confirmed'])
            ->delete();
    }
};
