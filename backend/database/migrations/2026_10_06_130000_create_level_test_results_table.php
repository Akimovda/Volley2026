<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_test_results', function (Blueprint $table) {
            $table->id();
            $table->string('source', 16)->index();          // web | telegram | max
            $table->string('nickname', 120)->nullable();    // @username / имя; у гостя сайта — NULL
            $table->string('discipline', 16);               // classic | beach
            $table->string('lang', 2)->default('ru');
            $table->unsignedSmallInteger('score');
            $table->string('level', 32)->index();
            $table->boolean('capped')->default(false);
            $table->timestampTz('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_test_results');
    }
};
