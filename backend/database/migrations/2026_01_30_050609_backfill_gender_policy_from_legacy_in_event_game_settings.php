<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Postgres: важно привести NULL к jsonb, иначе CASE -> text и падает.
        DB::statement("
            UPDATE event_game_settings
            SET
                gender_policy =
                    CASE
                        WHEN COALESCE(allow_girls, TRUE) = FALSE THEN 'only_male'
                        WHEN girls_max IS NOT NULL THEN 'mixed_limited'
                        ELSE 'mixed_open'
                    END,
                gender_limited_side =
                    CASE
                        WHEN COALESCE(allow_girls, TRUE) = FALSE THEN NULL
                        WHEN girls_max IS NOT NULL THEN 'female'
                        ELSE NULL
                    END,
                gender_limited_max =
                    CASE
                        WHEN COALESCE(allow_girls, TRUE) = FALSE THEN NULL
                        WHEN girls_max IS NOT NULL THEN girls_max
                        ELSE NULL
                    END,
		gender_limited_positions =
   			 CASE
       			 WHEN COALESCE(allow_girls, TRUE) = FALSE THEN NULL::jsonb
       			 WHEN girls_max IS NOT NULL THEN NULL::jsonb
		        ELSE NULL::jsonb
		    END
            WHERE gender_policy IS NULL OR gender_policy = ''
        ");
    }

    public function down(): void
    {
        // Откат backfill обычно не обязателен, но сделаем аккуратно.
        DB::statement("
            UPDATE event_game_settings
            SET
                gender_policy = NULL,
                gender_limited_side = NULL,
                gender_limited_max = NULL,
                gender_limited_positions = NULL::jsonb
        ");
    }
};
