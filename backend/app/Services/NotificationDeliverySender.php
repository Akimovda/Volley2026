<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class NotificationDeliverySender
{
    public function sendById(int $deliveryId): void
    {
        $delivery = DB::table('notification_deliveries')
            ->where('id', $deliveryId)
            ->first();

        if (!$delivery) {
            return;
        }

        if (!in_array((string) $delivery->status, ['pending', 'failed'], true)) {
            return;
        }

        $payload = $this->decodePayload($delivery->payload);
        $user = User::query()->find((int) $delivery->user_id);

        if (!$user) {
            $this->markFailed($deliveryId, 'Пользователь не найден.');
            return;
        }

        try {
            $channel = (string) $delivery->channel;

            if ($channel === 'in_app') {
                $this->markSent($deliveryId);
                return;
            }

            if ($channel === 'telegram') {
                $this->sendTelegram($user, $payload);
                $this->markSent($deliveryId);
                return;
            }

            if ($channel === 'vk') {
                $this->sendVk($user, $payload);
                $this->markSent($deliveryId);
                return;
            }

            if ($channel === 'max') {
                $this->sendMax($user, $payload);
                $this->markSent($deliveryId);
                return;
            }

            $this->markFailed($deliveryId, 'Неизвестный канал доставки.');
        } catch (\Throwable $e) {
            $this->markFailed($deliveryId, $e->getMessage());
        }
    }

    private function sendTelegram(User $user, array $payload): void
    {
        if (empty($user->telegram_id)) {
            throw new \RuntimeException('У пользователя нет telegram_id.');
        }

        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            throw new \RuntimeException('Не настроен services.telegram.bot_token.');
        }

        $rich = $this->buildRichPayload($payload);
        // Используем форматированный текст (без URL в теле — он идёт кнопкой)
        $text = $this->buildFormattedText($payload);

        $replyMarkup = null;
        if ($rich['button_url'] !== '') {
            $replyMarkup = [
                'inline_keyboard' => [[
                    [
                        'text' => $rich['button_text'] !== '' ? $rich['button_text'] : 'Подробнее',
                        'url'  => $rich['button_url'],
                    ],
                ]],
            ];
        }

        if ($rich['image_url'] !== '') {
            $resp = Http::timeout(20)->post(
                "https://api.telegram.org/bot{$token}/sendPhoto",
                array_filter([
                    'chat_id'      => (string) $user->telegram_id,
                    'photo'        => $rich['image_url'],
                    'caption'      => $text,
                    'parse_mode'   => $this->telegramParseMode($rich['format']),
                    'reply_markup' => $replyMarkup ? json_encode($replyMarkup, JSON_UNESCAPED_UNICODE) : null,
                ], fn ($v) => $v !== null)
            );

            if (!$resp->ok()) {
                throw new \RuntimeException('Telegram sendPhoto HTTP ' . $resp->status() . ': ' . $resp->body());
            }

            $json = $resp->json();
            if (!is_array($json) || !($json['ok'] ?? false)) {
                throw new \RuntimeException('Telegram sendPhoto API error: ' . $resp->body());
            }

            return;
        }

        $resp = Http::timeout(20)->post(
            "https://api.telegram.org/bot{$token}/sendMessage",
            array_filter([
                'chat_id'                  => (string) $user->telegram_id,
                'text'                     => $text,
                'parse_mode'               => $this->telegramParseMode($rich['format']),
                'disable_web_page_preview' => false,
                'reply_markup'             => $replyMarkup ? json_encode($replyMarkup, JSON_UNESCAPED_UNICODE) : null,
            ], fn ($v) => $v !== null)
        );

        if (!$resp->ok()) {
            throw new \RuntimeException('Telegram sendMessage HTTP ' . $resp->status() . ': ' . $resp->body());
        }

        $json = $resp->json();
        if (!is_array($json) || !($json['ok'] ?? false)) {
            throw new \RuntimeException('Telegram sendMessage API error: ' . $resp->body());
        }
    }

    private function sendVk(User $user, array $payload): void
    {
        // vk_notify_user_id — VK user_id, сохранённый когда пользователь написал боту notify_<token>
        // Для личных сообщений peer_id == user_id, поэтому передаём его напрямую как chat_id
        $vkUserId = trim((string) ($user->vk_notify_user_id ?? ''));
        if ($vkUserId === '') {
            throw new \RuntimeException('У пользователя не привязан VK-бот (vk_notify_user_id пуст).');
        }

        // Маршрутизируем через VK-бота (как VkChannelPublisher):
        // Laravel → POST /send на VK-бот → VK API messages.send
        // Бот сам держит community token и строит keyboard
        $endpoint = rtrim((string) config('services.vk.bot_api_url'), '/');
        if ($endpoint === '') {
            throw new \RuntimeException('Не настроен services.vk.bot_api_url.');
        }

        $secret = (string) config('services.bind.secret');
        if ($secret === '') {
            throw new \RuntimeException('Не настроен BIND_WEBHOOK_SECRET.');
        }

        $rich       = $this->buildRichPayload($payload);
        $text       = $this->buildFormattedText($payload);
        $buttonUrl  = $rich['button_url'];
        $buttonText = $rich['button_text'] !== '' ? $rich['button_text'] : ($buttonUrl !== '' ? 'Подробнее' : '');

        $resp = Http::timeout(20)
            ->withHeaders([
                'X-Bind-Secret' => $secret,
                'Accept'        => 'application/json',
            ])
            ->post($endpoint . '/send', array_filter([
                'chat_id'     => $vkUserId,
                'text'        => $text,
                'button_url'  => $buttonUrl !== '' ? $buttonUrl : null,
                'button_text' => $buttonText !== '' ? $buttonText : null,
                'image_url'   => $rich['image_url'] !== '' ? $rich['image_url'] : null,
            ], fn ($v) => $v !== null));

        if (!$resp->ok()) {
            throw new \RuntimeException('VK bot HTTP ' . $resp->status() . ': ' . $resp->body());
        }

        $json = $resp->json();
        if (!is_array($json) || empty($json['ok'])) {
            throw new \RuntimeException('VK bot error: ' . $resp->body());
        }
    }

    private function sendMax(User $user, array $payload): void
    {
        if (empty($user->max_chat_id)) {
            throw new \RuntimeException('У пользователя нет max_chat_id.');
        }

        $token = (string) config('services.max.bot_token');
        if ($token === '') {
            throw new \RuntimeException('Не настроен services.max.bot_token.');
        }

        $rich   = $this->buildRichPayload($payload);
        $chatId = (string) $user->max_chat_id;

        if ($rich['image_url'] !== '') {
            $this->sendMaxImageByUrl($token, $chatId, $rich['image_url']);
        }

        // Форматированный текст без дублирования ссылки в теле
        $text = $this->buildFormattedText($payload);

        $body = ['text' => $text];

        // Inline-кнопка (как у Telegram)
        if ($rich['button_url'] !== '') {
            $buttonText = $rich['button_text'] !== '' ? $rich['button_text'] : 'Подробнее';
            $body['attachments'] = [[
                'type'    => 'inline_keyboard',
                'payload' => [
                    'buttons' => [[
                        [
                            'type' => 'link',
                            'text' => $buttonText,
                            'url'  => $rich['button_url'],
                        ],
                    ]],
                ],
            ]];
        }

        $resp = Http::timeout(20)
            ->withHeaders([
                'Authorization' => $token,
                'Content-Type'  => 'application/json',
            ])
            ->post('https://platform-api.max.ru/messages?chat_id=' . urlencode($chatId), $body);

        if (!$resp->ok()) {
            throw new \RuntimeException('MAX HTTP ' . $resp->status() . ': ' . $resp->body());
        }
    }

    // -------------------------------------------------------------------------
    // Форматирование текста
    // -------------------------------------------------------------------------

    /**
     * Строит красиво оформленное сообщение с эмодзи из структурированных данных
     * (occurrence_datetime, location_full / location_address), которые теперь
     * сохраняются в payload доставки через UserNotificationService.
     *
     * URL намеренно НЕ добавляется в текст — он идёт отдельной кнопкой.
     */
    private function buildFormattedText(array $payload): string
    {
        $rich = $this->buildRichPayload($payload);
        $type = (string) ($payload['template_code'] ?? '');

        // ---- Эмодзи по типу уведомления ----
        $emoji = match ($type) {
            'registration_created'                                  => '✅',
            'registration_cancelled',
            'registration_cancelled_by_organizer'                   => '❌',
            'event_reminder'                                        => '⏰',
            'event_cancelled', 'event_cancelled_quorum'             => '🚫',
            'group_invite', 'tournament_team_invite'                => '🤝',
            'event_invite'                                          => '🏐',
            default                                                 => '📢',
        };

        $lines = [];
        $lines[] = $emoji . ' ' . $rich['title'];

        // ---- Дата/время ----
        $datetime = trim((string) (
            $payload['occurrence_datetime']
            ?? $payload['event_datetime']
            ?? $payload['occurrence_date'] . ($payload['occurrence_time'] ? ' ' . $payload['occurrence_time'] : '')
            ?? ''
        ));
        if ($datetime !== '') {
            $lines[] = '📆 Дата: ' . $datetime;
        }

        // ---- Адрес ----
        $address = trim((string) ($payload['location_full'] ?? $payload['location_address'] ?? ''));
        if ($address !== '') {
            $lines[] = '📍 Адрес: ' . $address;
        }

        // ---- Дополнительный текст (для типов с body-сообщением, напр. group_invite) ----
        $extra = $this->extractExtraBody($rich['body'], $rich['button_url'], $type);
        if ($extra !== '') {
            $lines[] = '';
            $lines[] = $extra;
        }

        return implode("\n", $lines);
    }

    /**
     * Извлекает «дополнительный» текст из body для типов, где body несёт
     * смысловую нагрузку (group_invite, tournament_team_invite и т.п.).
     * Для структурированных типов возвращает ''.
     *
     * Из body удаляются строки с URL и шаблонные строки типа "Дата: ...",
     * "Адрес: ...", "Открыть мероприятие:" — они формируются из структурных полей выше.
     */
    private function extractExtraBody(string $body, string $buttonUrl, string $type): string
    {
        // Для структурированных типов body полностью заменяем на поля выше
        $structuredTypes = [
            'registration_created',
            'registration_cancelled',
            'registration_cancelled_by_organizer',
            'event_cancelled',
            'event_cancelled_quorum',
            'event_reminder',
        ];

        if (in_array($type, $structuredTypes, true) || $body === '') {
            return '';
        }

        // Удаляем строки с URL
        if ($buttonUrl !== '') {
            $body = str_replace($buttonUrl, '', $body);
        }

        // Удаляем шаблонные строки, которые уже показаны выше
        $body = preg_replace('/^(Дата|Дата и время|Date)\s*:.*$/mu', '', $body ?? '');
        $body = preg_replace('/^(Адрес|Address)\s*:.*$/mu', '', $body ?? '');
        $body = preg_replace('/^(Открыть|Подробнее|открыть)[^\n]*/mu', '', $body ?? '');

        // Схлопываем пустые строки
        $body = preg_replace('/\n{3,}/', "\n\n", $body ?? '');

        return trim($body ?? '');
    }

    // -------------------------------------------------------------------------
    // Вспомогательные методы (без изменений)
    // -------------------------------------------------------------------------

    private function sendMaxImageByUrl(string $token, string $chatId, string $imageUrl): void
    {
        $file = Http::timeout(30)->get($imageUrl);
        if (!$file->ok()) {
            throw new \RuntimeException('MAX image download HTTP ' . $file->status());
        }

        $uploadMeta = Http::timeout(20)
            ->withHeaders(['Authorization' => $token])
            ->post('https://platform-api.max.ru/uploads?type=image');

        if (!$uploadMeta->ok()) {
            throw new \RuntimeException('MAX uploads HTTP ' . $uploadMeta->status() . ': ' . $uploadMeta->body());
        }

        $uploadJson = $uploadMeta->json();
        $uploadUrl  = (string) ($uploadJson['url'] ?? '');
        if ($uploadUrl === '') {
            throw new \RuntimeException('MAX uploads не вернул url.');
        }

        $tmp = tmpfile();
        if ($tmp === false) {
            throw new \RuntimeException('Не удалось создать tmpfile для MAX image.');
        }

        fwrite($tmp, $file->body());
        $meta = stream_get_meta_data($tmp);
        $path = $meta['uri'];

        $uploaded = Http::timeout(30)
            ->attach('data', file_get_contents($path), 'image.jpg')
            ->post($uploadUrl);

        fclose($tmp);

        if (!$uploaded->ok()) {
            throw new \RuntimeException('MAX upload image HTTP ' . $uploaded->status() . ': ' . $uploaded->body());
        }

        $uploadPayload = $uploaded->json();
        if (!is_array($uploadPayload) || empty($uploadPayload)) {
            throw new \RuntimeException('MAX upload image вернул пустой payload.');
        }

        usleep(700000);

        $resp = Http::timeout(20)
            ->withHeaders([
                'Authorization' => $token,
                'Content-Type'  => 'application/json',
            ])
            ->post('https://platform-api.max.ru/messages?chat_id=' . urlencode($chatId), [
                'text'        => '',
                'attachments' => [[
                    'type'    => 'image',
                    'payload' => $uploadPayload,
                ]],
            ]);

        if ($resp->ok()) {
            return;
        }

        $body = (string) $resp->body();
        if (str_contains($body, 'attachment.not.ready')) {
            usleep(1500000);

            $retry = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => $token,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://platform-api.max.ru/messages?chat_id=' . urlencode($chatId), [
                    'text'        => '',
                    'attachments' => [[
                        'type'    => 'image',
                        'payload' => $uploadPayload,
                    ]],
                ]);

            if ($retry->ok()) {
                return;
            }

            throw new \RuntimeException('MAX image retry HTTP ' . $retry->status() . ': ' . $retry->body());
        }

        throw new \RuntimeException('MAX image message HTTP ' . $resp->status() . ': ' . $body);
    }

    private function buildRichPayload(array $payload): array
    {
        return [
            'title'      => trim((string) ($payload['title'] ?? 'Уведомление')),
            'body'       => trim((string) ($payload['body'] ?? '')),
            'image_url'  => trim((string) ($payload['image_url'] ?? '')),
            'button_text'=> trim((string) ($payload['button_text'] ?? '')),
            'button_url' => trim((string) ($payload['button_url'] ?? '')),
            'format'     => trim((string) ($payload['format'] ?? 'plain')),
        ];
    }

    private function telegramParseMode(string $format): ?string
    {
        return match (mb_strtolower($format)) {
            'html'                   => 'HTML',
            'markdown', 'markdownv2' => 'MarkdownV2',
            default                  => null,
        };
    }

    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function markSent(int $deliveryId): void
    {
        DB::table('notification_deliveries')
            ->where('id', $deliveryId)
            ->update([
                'status'     => 'sent',
                'sent_at'    => now(),
                'error'      => null,
                'updated_at' => now(),
            ]);
    }

    private function markFailed(int $deliveryId, string $error): void
    {
        DB::table('notification_deliveries')
            ->where('id', $deliveryId)
            ->update([
                'status'     => 'failed',
                'error'      => mb_substr($error, 0, 4000),
                'updated_at' => now(),
            ]);

        Log::warning('Notification delivery failed', [
            'delivery_id' => $deliveryId,
            'error'       => $error,
        ]);
    }
}