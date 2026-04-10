<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscription_coupon_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 20); // subscription|coupon
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('user_id')->nullable(); // кто совершил действие
            $table->string('action', 30);
            // purchased|issued|used|returned|burned|frozen|unfrozen|transferred|expired|cancelled|extended
            $table->json('payload')->nullable(); // доп. данные
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('user_id');
        });
    }
    public function down(): void { Schema::dropIfExists('subscription_coupon_logs'); }
};
