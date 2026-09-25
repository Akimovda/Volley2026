<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_links', function (Blueprint $table) {
            // 'merge' — псевдоним появился при слиянии дублей (UserMergeService::merge()),
            // provider_user_id secondary-аккаунта сохранён здесь вместо потери, т.к. у primary
            // уже был свой provider id. Nullable — для возможных будущих источников без
            // привязки к конкретному secondary-аккаунту.
            $table->string('source')->nullable()->after('provider_email');

            $table->foreignId('merged_from_user_id')
                ->nullable()
                ->after('source')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_from_user_id');
            $table->dropColumn('source');
        });
    }
};
