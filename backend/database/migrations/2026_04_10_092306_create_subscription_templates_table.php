<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscription_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
            $table->string('name', 150);
            $table->text('description')->nullable();

            // Мероприятия — храним как JSON массив event_id
            $table->json('event_ids')->nullable(); // null = все мероприятия организатора

            // Срок действия шаблона
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            // Посещения
            $table->unsignedSmallInteger('visits_total'); // кол-во посещений

            // Сгорание при отмене
            $table->unsignedSmallInteger('cancel_hours_before')->default(0); // 0 = не сгорает

            // Заморозка
            $table->boolean('freeze_enabled')->default(false);
            $table->unsignedTinyInteger('freeze_max_weeks')->default(0);
            $table->unsignedTinyInteger('freeze_max_months')->default(0);

            // Передача
            $table->boolean('transfer_enabled')->default(false);

            // Автозапись
            $table->boolean('auto_booking_enabled')->default(false);

            // Стоимость
            $table->unsignedInteger('price_minor')->default(0); // копейки
            $table->string('currency', 3)->default('RUB');

            // Лимит продаж
            $table->unsignedInteger('sale_limit')->nullable(); // null = безлимит
            $table->unsignedInteger('sold_count')->default(0);

            // Продажа на сайте
            $table->boolean('sale_enabled')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organizer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['organizer_id', 'is_active']);
        });
    }
    public function down(): void { Schema::dropIfExists('subscription_templates'); }
};
