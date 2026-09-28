<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volleyball_schools', function (Blueprint $table) {
            $table->jsonb('cover_media_ids')->nullable()->after('cover_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('volleyball_schools', function (Blueprint $table) {
            $table->dropColumn('cover_media_ids');
        });
    }
};
