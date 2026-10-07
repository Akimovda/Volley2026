<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // «Пустышка» — аккаунт без точек входа, созданный организатором/админом
            // для составов команд и игр; позже сливается с реальным игроком.
            $table->boolean('is_placeholder')->default(false)->after('is_bot');
            $table->foreignId('created_by_user_id')->nullable()->after('is_placeholder')
                ->constrained('users')->nullOnDelete();
            $table->index(['is_placeholder', 'created_by_user_id'], 'users_placeholder_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_placeholder_owner_idx');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn('is_placeholder');
        });
    }
};
