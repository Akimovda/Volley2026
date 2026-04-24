<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->after('event_id');
            $table->text('description_html')->nullable()->after('show_participants');

            $table->boolean('is_paid')->nullable()->after('description_html');
            $table->bigInteger('price_minor')->nullable()->after('is_paid');
            $table->string('price_currency', 3)->nullable()->after('price_minor');
            $table->string('price_text', 255)->nullable()->after('price_currency');
            $table->string('payment_method', 30)->nullable()->after('price_text');
            $table->text('payment_link')->nullable()->after('payment_method');

            $table->smallInteger('refund_hours_full')->nullable()->after('payment_link');
            $table->smallInteger('refund_hours_partial')->nullable()->after('refund_hours_full');
            $table->smallInteger('refund_partial_pct')->nullable()->after('refund_hours_partial');

            $table->foreignId('trainer_user_id')->nullable()->after('refund_partial_pct')
                  ->constrained('users')->nullOnDelete();

            $table->boolean('requires_personal_data')->nullable()->after('trainer_user_id');
            $table->smallInteger('child_age_min')->nullable()->after('requires_personal_data');
            $table->smallInteger('child_age_max')->nullable()->after('child_age_min');
        });
    }

    public function down(): void
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trainer_user_id');
            $table->dropColumn([
                'title', 'description_html',
                'is_paid', 'price_minor', 'price_currency', 'price_text',
                'payment_method', 'payment_link',
                'refund_hours_full', 'refund_hours_partial', 'refund_partial_pct',
                'requires_personal_data', 'child_age_min', 'child_age_max',
            ]);
        });
    }
};
