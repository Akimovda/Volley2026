<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Последний порог «осталось N дней», о котором уже уведомили (30/7/1; 0 — подписка закончилась).
        // NULL — ещё ничего не слали. Отдельная колонка, чтобы повторные запуски команды не дублировали уведомления.
        Schema::table('organizer_subscriptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('expiry_notice_days')->nullable()->after('auto_renew');
        });

        $now = now();
        $codes = [
            'organizer_pro_expiring' => 'Организатор Pro скоро закончится (напоминание за N дней)',
            'organizer_pro_expired'  => 'Организатор Pro закончился',
        ];
        $existing = DB::table('notification_templates')->whereIn('code', array_keys($codes))->pluck('code')->all();
        $rows = [];
        foreach ($codes as $code => $name) {
            if (in_array($code, $existing, true)) {
                continue;
            }
            $rows[] = [
                'code'           => $code,
                'channel'        => null,
                'name'           => $name,
                'title_template' => null,
                'body_template'  => null,
                'is_active'      => false,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }
        if ($rows) {
            DB::table('notification_templates')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('code', ['organizer_pro_expiring', 'organizer_pro_expired'])->delete();

        Schema::table('organizer_subscriptions', function (Blueprint $table) {
            $table->dropColumn('expiry_notice_days');
        });
    }
};
