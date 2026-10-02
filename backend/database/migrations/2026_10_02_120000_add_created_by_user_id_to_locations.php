<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('locations', 'created_by_user_id')) {
            Schema::table('locations', function (Blueprint $table) {
                // Кто создал локацию (организатор через мастер мероприятия). NULL = создана админом/легаси.
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (!DB::table('notification_templates')->where('code', 'location_created_by_organizer')->exists()) {
            DB::table('notification_templates')->insert([
                'code' => 'location_created_by_organizer', 'channel' => null,
                'name' => 'Админу: организатор создал новую локацию',
                'title_template' => null, 'body_template' => null,
                'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->where('code', 'location_created_by_organizer')
            ->where('is_active', false)->delete();
        if (Schema::hasColumn('locations', 'created_by_user_id')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by_user_id');
            });
        }
    }
};
