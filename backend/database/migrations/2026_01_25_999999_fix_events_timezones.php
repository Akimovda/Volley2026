<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        // 1) Если timezone где-то null — сделаем безопасно
        if (Schema::hasColumn('events', 'timezone')) {
            DB::statement("UPDATE events SET timezone = 'UTC' WHERE timezone IS NULL");
            DB::statement("ALTER TABLE events ALTER COLUMN timezone SET DEFAULT 'UTC'");
        }

        // 2) starts_at: timestamp (без TZ) -> timestamptz
        // ВАЖНО: этот USING трактует старое starts_at как "локальное время" в timezone.
        // Это корректно, если ранее ты сохранял starts_at как local time, а timezone отдельно.
        if (Schema::hasColumn('events', 'starts_at')) {
            DB::statement("
                ALTER TABLE events
                ALTER COLUMN starts_at TYPE timestamptz
                USING (starts_at AT TIME ZONE COALESCE(timezone, 'UTC'))
            ");
        }

        // 3) ends_at у тебя уже timestamptz (по tinker) — не трогаем.
        // Но если вдруг где-то оказалось timestamp без TZ — можно раскомментить:
        /*
        if (Schema::hasColumn('events', 'ends_at')) {
            DB::statement("
                ALTER TABLE events
                ALTER COLUMN ends_at TYPE timestamptz
                USING (ends_at AT TIME ZONE COALESCE(timezone, 'UTC'))
            ");
        }
        */
    }

    public function down(): void
    {
        // Откат intentionally пустой: откат timestamptz->timestamp часто портит данные.
    }
};
