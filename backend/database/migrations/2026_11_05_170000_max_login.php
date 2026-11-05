<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Вход и привязка через MAX (по ссылке-приглашению к боту):
 *  - users.max_user_id — идентификатор пользователя MAX (личность для входа; max_chat_id — диалог с ботом для уведомлений);
 *    частичный уникальный индекс только по живым пользователям, как у остальных провайдеров;
 *  - max_login_attempts — попытки входа: одноразовый токен в ссылке (хранится только его sha256), код подтверждения,
 *    который показывается на сайте и вводится в чате с ботом (защита от «пересылки ссылки жертве»).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'max_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('max_user_id', 64)->nullable();
            });
            DB::statement('CREATE UNIQUE INDEX users_max_user_id_unique ON users (max_user_id) WHERE deleted_at IS NULL AND max_user_id IS NOT NULL');
        }

        if (!Schema::hasTable('max_login_attempts')) {
            Schema::create('max_login_attempts', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 40)->unique();          // идентификатор для опроса из браузера
                $table->char('secret_hash', 64)->unique();          // sha256 токена из ссылки (сам токен не хранится)
                $table->char('code', 4);                            // код, который видит только браузер, начавший вход
                $table->string('status', 16)->default('pending');   // pending|awaiting_code|approved|used|expired|denied
                $table->string('intent', 8)->default('login');      // login|link
                $table->unsignedBigInteger('user_id')->nullable();  // для intent=link — кто привязывает
                $table->string('max_user_id', 64)->nullable();
                $table->string('max_chat_id', 64)->nullable();
                $table->string('max_name', 191)->nullable();
                $table->string('max_username', 191)->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->text('return_to')->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestampTz('expires_at');
                $table->timestampTz('approved_at')->nullable();
                $table->timestampsTz();
                $table->index(['max_user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('max_login_attempts');
        DB::statement('DROP INDEX IF EXISTS users_max_user_id_unique');
        if (Schema::hasColumn('users', 'max_user_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('max_user_id'));
        }
    }
};
