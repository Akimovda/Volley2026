<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            UPDATE locations l
            SET city_id = c.id
            FROM cities c
            WHERE l.city_id IS NULL
              AND l.city IS NOT NULL
              AND LOWER(TRIM(l.city)) = LOWER(TRIM(c.name))
        ");
    }

    public function down(): void
    {
        // no-op
    }
};
