<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE premium_subscriptions DROP CONSTRAINT IF EXISTS premium_subscriptions_plan_check");
        DB::statement("ALTER TABLE premium_subscriptions ADD CONSTRAINT premium_subscriptions_plan_check CHECK (plan IN ('trial', 'month', 'quarter', 'half', 'year'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE premium_subscriptions DROP CONSTRAINT IF EXISTS premium_subscriptions_plan_check");
        DB::statement("ALTER TABLE premium_subscriptions ADD CONSTRAINT premium_subscriptions_plan_check CHECK (plan IN ('trial', 'month', 'quarter', 'year'))");
    }
};
