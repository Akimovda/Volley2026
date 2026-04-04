<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
   public function up(): void
{
    DB::statement("
        WITH best AS (
            SELECT
                LOWER(TRIM(name)) AS key_name,
                id,
                ROW_NUMBER() OVER (
                    PARTITION BY LOWER(TRIM(name))
                    ORDER BY COALESCE(population, 0) DESC, id DESC
                ) AS rn
            FROM cities
            WHERE name IS NOT NULL AND TRIM(name) <> ''
        )
        UPDATE locations
        SET city_id = b.id
        FROM best b, cities c
        WHERE locations.city_id IS NOT NULL
          AND c.id = locations.city_id
          AND b.rn = 1
          AND LOWER(TRIM(c.name)) = b.key_name
          AND locations.city_id <> b.id
    ");
}

    public function down(): void
    {
        // no-op
    }
};
