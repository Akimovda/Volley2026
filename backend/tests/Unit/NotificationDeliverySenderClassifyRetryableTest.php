<?php

namespace Tests\Unit;

use App\Services\NotificationDeliverySender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Фикстуры взяты из реальных текстов ошибок notification_deliveries на проде
 * (аудит 2026-07-09) — чтобы правка сигнатур в classifyRetryable() не сломала
 * молча уже проверенную классификацию.
 */
class NotificationDeliverySenderClassifyRetryableTest extends TestCase
{
    private function classify(string $error): bool
    {
        $sender = new NotificationDeliverySender();
        $ref = new \ReflectionMethod($sender, 'classifyRetryable');
        $ref->setAccessible(true);

        return $ref->invoke($sender, $error);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function prodFixturesProvider(): array
    {
        return [
            'vk: cURL error 6 (легаси-хост vk-bot)' => [
                'cURL error 6: Could not resolve host: vk-bot.volleyplay.club (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for http://vk-bot.volleyplay.club/send',
                true,
            ],
            'telegram: chat not found' => [
                'Telegram sendMessage HTTP 400: {"ok":false,"error_code":400,"description":"Bad Request: chat not found"}',
                false,
            ],
            'max: chat.not.found (формат с точками)' => [
                'MAX HTTP 404: {"code":"chat.not.found","message":"Chat 228699486 not found"}',
                false,
            ],
            "telegram: bot can't initiate conversation" => [
                "Telegram sendMessage HTTP 403: {\"ok\":false,\"error_code\":403,\"description\":\"Forbidden: bot can't initiate conversation with a user\"}",
                false,
            ],
            'telegram: user is deactivated' => [
                'Telegram sendMessage HTTP 403: {"ok":false,"error_code":403,"description":"Forbidden: user is deactivated"}',
                false,
            ],
            'telegram: cURL error 7 (сеть)' => [
                "cURL error 7: Failed to connect to api.telegram.org port 443 after 15 ms: Couldn't connect to server (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://api.telegram.org/bot8743303329",
                true,
            ],
            'telegram: cURL error 28 (SSL timeout)' => [
                'cURL error 28: SSL connection timeout (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://api.telegram.org/bot8743303329:AAF7vzWay2wQAUWTMSlhm3BaUegzqjAq1fA/sendMessage',
                true,
            ],
            'telegram: bot was blocked by the user' => [
                'Telegram sendMessage HTTP 403: {"ok":false,"error_code":403,"description":"Forbidden: bot was blocked by the user"}',
                false,
            ],
            'max: chat.denied / dialog.suspended' => [
                'MAX HTTP 403: {"code":"chat.denied","message":"Key: error.dialog.suspended, args: [98423496,]."}',
                false,
            ],
            'vk: cURL error 7 (легаси-порт 127.0.0.1:8095)' => [
                "cURL error 7: Failed to connect to 127.0.0.1 port 8095 after 0 ms: Couldn't connect to server (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for http://127.0.0.1:8095/send",
                true,
            ],
        ];
    }

    #[DataProvider('prodFixturesProvider')]
    public function test_classifies_real_prod_error_texts_correctly(string $error, bool $expectedRetryable): void
    {
        $this->assertSame($expectedRetryable, $this->classify($error));
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function vkErrorCodesProvider(): array
    {
        return [
            'VK 7 — нет прав доступа'                          => [7, false],
            'VK 15 — доступ запрещён'                           => [15, false],
            'VK 902 — нельзя отправить сообщение этому юзеру'   => [902, false],
            'VK 6 — rate limit'                                 => [6, true],
            'VK 10 — внутренняя ошибка сервера'                 => [10, true],
        ];
    }

    #[DataProvider('vkErrorCodesProvider')]
    public function test_classifies_vk_error_codes_by_number(int $vkErrorCode, bool $expectedRetryable): void
    {
        $error = 'VK API error: ' . json_encode([
            'error_code' => $vkErrorCode,
            'error_msg'  => 'synthetic test message',
        ], JSON_UNESCAPED_UNICODE);

        $this->assertSame($expectedRetryable, $this->classify($error));
    }

    public function test_unknown_error_defaults_to_retryable(): void
    {
        $this->assertTrue($this->classify('Совершенно новый неизвестный формат ошибки, которого раньше не было'));
    }
}
