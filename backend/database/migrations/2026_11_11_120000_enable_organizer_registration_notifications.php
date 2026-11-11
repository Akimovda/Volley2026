<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Уведомления организатору о записях игроков (users.notify_player_registrations) по умолчанию выключены у всех,
 * из-за чего организаторы с подключённым мессенджером ничего не получали. Включаем их организаторам и помощникам
 * (role organizer/staff и участникам organizer_staff); вручную выключить можно в профиле.
 * Список затронутых пользователей сохраняем для отката.
 */
return new class extends Migration {
    public function up(): void
    {
        $ids = DB::table('users')
            ->where('notify_player_registrations', false)
            ->where(function ($q) {
                $q->whereIn('role', ['organizer', 'staff'])
                  ->orWhereIn('id', DB::table('organizer_staff')->select('staff_user_id'));
            })
            ->pluck('id')->all();

        if ($ids) {
            Storage::disk('local')->put('migrations/notify_player_registrations_enabled_2026-11-11.json', json_encode($ids));
            DB::table('users')->whereIn('id', $ids)->update(['notify_player_registrations' => true]);
        }
    }

    public function down(): void
    {
        $f = 'migrations/notify_player_registrations_enabled_2026-11-11.json';
        if (Storage::disk('local')->exists($f)) {
            $ids = json_decode(Storage::disk('local')->get($f), true) ?: [];
            if ($ids) {
                DB::table('users')->whereIn('id', $ids)->update(['notify_player_registrations' => false]);
            }
        }
    }
};
