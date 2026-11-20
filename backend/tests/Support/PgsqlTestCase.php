<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * База для доменных тестов на PostgreSQL (локальная тестовая БД volley_test, каждая проверка — в откатываемой транзакции).
 * Запуск: tools/test-pgsql.sh (см. docs/auto-quorum.md, раздел «Тесты»).
 *
 * Предохранитель: если подключение не localhost/volley_test — тест пропускается (никогда не ходим в прод-БД).
 * Внешние вызовы отрезаны: Http::fake() (Telegram/VK/MAX/ЮKassa), Queue::fake() (джобы уведомлений/анонсов
 * только фиксируются, не выполняются), Sentry отвязан.
 */
abstract class PgsqlTestCase extends TestCase
{
    use DatabaseTransactions;
    use BuildsGameEvents;

    protected function setUp(): void
    {
        parent::setUp();

        $conn = config('database.default');
        $cfg  = (array) config("database.connections.{$conn}");
        if ($conn !== 'pgsql'
            || !in_array((string) ($cfg['host'] ?? ''), ['127.0.0.1', 'localhost', '::1'], true)
            || (string) ($cfg['database'] ?? '') !== 'volley_test') {
            $this->markTestSkipped('Нужна локальная тестовая БД pgsql volley_test (запуск: tools/test-pgsql.sh).');
        }

        \Sentry\SentrySdk::getCurrentHub()->bindClient(\Sentry\ClientBuilder::create(['dsn' => null])->getClient());
        Http::fake();
        Queue::fake();
    }
}
