<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL не поддерживает ALTER TYPE напрямую — меняем через raw SQL
        DB::statement("ALTER TABLE premium_subscriptions DROP CONSTRAINT IF EXISTS premium_subscriptions_status_check");
        DB::statement("ALTER TABLE premium_subscriptions ADD CONSTRAINT premium_subscriptions_status_check CHECK (status IN ('active', 'expired', 'cancelled', 'pending'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE premium_subscriptions DROP CONSTRAINT IF EXISTS premium_subscriptions_status_check");
        DB::statement("ALTER TABLE premium_subscriptions ADD CONSTRAINT premium_subscriptions_status_check CHECK (status IN ('active', 'expired', 'cancelled'))");
    }
};
