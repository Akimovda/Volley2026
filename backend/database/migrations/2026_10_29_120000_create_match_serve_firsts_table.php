<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Пляжка 2×2: кто подаёт ПЕРВЫМ у пары в партии (порядок подачи в паре — по очереди, меняется при side-out).
 * Пара, подающая первой в партии, получает строку тапом в блоке «Подаёт первым»; вторая пара — один раз вопросом
 * «Кто подаёт у <пара>?» при первом получении подачи. Команда-первая по-прежнему хранится в match_serve_marks.
 * Дальше порядок выводится из очков (rotationState): отмена очков откатывает очередь, повтор client_request_id безвреден.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_serve_firsts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->foreignId('team_id')->constrained('event_teams')->cascadeOnDelete();
            $table->foreignId('first_server_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('client_request_id')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('recorded_by_link_id')->nullable();
            $table->string('recorded_by_name')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'set_number', 'team_id'], 'msf_match_set_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_serve_firsts');
    }
};
