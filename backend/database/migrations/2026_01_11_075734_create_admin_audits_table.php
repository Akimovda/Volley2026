<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_audits', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('actor_user_id')->index(); // кто сделал
            $table->string('action', 64)->index();                // что сделал

            // универсальная ссылка на цель действия (можно null)
            $table->string('target_type', 64)->nullable()->index(); // например: 'user', 'event'
            $table->unsignedBigInteger('target_id')->nullable()->index();

            // удобные поля для расследований
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();

            // json с деталями (роль до/после, фильтры поиска, etc)
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->foreign('actor_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audits');
    }
};
