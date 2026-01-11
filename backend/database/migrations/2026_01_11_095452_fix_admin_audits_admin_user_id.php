<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ничего не ломаем: делаем миграцию идемпотентной
        if (!Schema::hasTable('admin_audits')) {
            return;
        }

        $hasAdmin = Schema::hasColumn('admin_audits', 'admin_user_id');
        $hasActor = Schema::hasColumn('admin_audits', 'actor_user_id');

        // Если уже всё ок — выходим
        if ($hasAdmin) {
            return;
        }

        // Если есть actor_user_id — переименуем в admin_user_id
        if ($hasActor) {
            // Postgres-safe rename
            DB::statement('ALTER TABLE admin_audits RENAME COLUMN actor_user_id TO admin_user_id');
            return;
        }

        // Если нет ни одной — создадим admin_user_id (на всякий случай)
        Schema::table('admin_audits', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('admin_audits')) {
            return;
        }

        $hasAdmin = Schema::hasColumn('admin_audits', 'admin_user_id');
        $hasActor = Schema::hasColumn('admin_audits', 'actor_user_id');

        // Откат: если admin_user_id есть, а actor_user_id нет — вернём имя назад
        if ($hasAdmin && !$hasActor) {
            DB::statement('ALTER TABLE admin_audits RENAME COLUMN admin_user_id TO actor_user_id');
        }
    }
};
