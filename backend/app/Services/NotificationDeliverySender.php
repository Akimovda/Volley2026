<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class NotificationDeliverySender
{
    /**
     * @param bool $isRetry true — вызов из notifications:retry-failed (инкрементит attempts).
     *                      false — исходная попытка из SendNotificationDeliveryJob
     *                      (attempts не трогаем — это ещё не "повтор").
     */
    public function sendById(int $deliveryId, bool $isRetry = false): void
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
            $this->markFailed($deliveryId, 'Пользователь не найден.', $isRetry);
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

            if ($channel === 'push') {
                $this->sendPush($user, $payload);
                $this->markSent($deliveryId);
                return;
            }

            $this->markFailed($deliveryId, 'Неизвестный канал доставки.', $isRetry);
        } catch (\Throwable $e) {
            $this->markFailed($deliveryId, $e->getMessage(), $isRetry);
        }
    }

    private function sendPush(User $user, array $payload): void
    {
        $service = app(\App\Services\PushNotificationService::class);
        $service->send(
            userId: (int) $user->id,
            title:  (string) ($payload['title'] ?? 'Уведомление'),
            body:   $this->cleanBodyForPush((string) ($payload['body'] ?? '')),
            data:   array_filter([
                'type'          => $payload['template_code'] ?? null,
                'event_id'      => $payload['event_id'] ?? null,
                'occurrence_id' => $payload['occurrence_id'] ?? null,
                'button_url'    => $payload['button_url'] ?? null,
            ], fn ($v) => $v !== null)
        );
    }

    private function cleanBodyForPush(string $body): string
    {
        if ($body === '') return '';
        $lines = explode("\n", $body);
        $lines = array_filter($lines, function (string $line): bool {
            $line = trim($line);
            if ($line === '') return false;
            if (str_contains($line, 'http://') || str_contains($line, 'https://')) return false;
            if (str_contains($line, 'Открыть мероприятие')) return false;
            if (str_contains($line, 'Смотрите и делитесь')) return false;
            if (str_contains($line, 'Подробности')) return false;
            if (preg_match('/^[^:]+:\s*$/', $line)) return false;
            return true;
        });
        return trim(implode("\n", $lines));
    }

    private function sendTelegram(User $user, array $payload): void
    {
        // telegram_notify_chat_id — реальный chat_id личного диалога с ботом, появляется
        // только после /start notify_<token>. telegram_id (OAuth-логин) НЕ годится как
        // chat_id — Telegram не разрешает боту первым писать пользователю, который
        // никогда не открывал с ним чат: "chat not found"/"bot can't initiate conversation".
        if (empty($user->telegram_notify_chat_id)) {
            throw new \RuntimeException('У пользователя не подключены Telegram-уведомления (telegram_notify_chat_id пуст).');
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
                    'chat_id'      => (string) $user->telegram_notify_chat_id,
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
                'chat_id'                  => (string) $user->telegram_notify_chat_id,
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
        $vkUserId = trim((string) ($user->vk_notify_user_id ?? ''));
        if ($vkUserId === '') {
            throw new \RuntimeException('У пользователя не привязан VK-бот (vk_notify_user_id пуст).');
        }

        $token = (string) config('services.vk.community_token');
        if ($token === '') {
            throw new \RuntimeException('Не настроен VK_COMMUNITY_TOKEN.');
        }

        $rich       = $this->buildRichPayload($payload);
        $text       = $this->buildFormattedText($payload);
        $buttonUrl  = $rich['button_url'];
        $buttonText = $rich['button_text'] !== '' ? $rich['button_text'] : ($buttonUrl !== '' ? 'Подробнее' : '');

        $params = [
            'peer_id'      => (int) $vkUserId,
            'message'      => $text,
            'random_id'    => random_int(1, PHP_INT_MAX),
            'access_token' => $token,
            'v'            => config('services.vk.community_v', '5.199'),
        ];

        if ($buttonUrl !== '' && $buttonText !== '') {
            $params['keyboard'] = json_encode([
                'inline'  => true,
                'buttons' => [[
                    [
                        'action' => [
                            'type'  => 'open_link',
                            'link'  => $buttonUrl,
                            'label' => mb_substr($buttonText, 0, 40),
                        ],
                    ],
                ]],
            ], JSON_UNESCAPED_UNICODE);
        }

        $resp = Http::timeout(10)
            ->asForm()
            ->post('https://api.vk.com/method/messages.send', $params);

        $json = $resp->json();
        if (!empty($json['error'])) {
            throw new \RuntimeException('VK API error: ' . json_encode($json['error'], JSON_UNESCAPED_UNICODE));
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
            'registration_created'                                       => '✅',
            'registration_cancelled',
            'registration_cancelled_by_organizer'                        => '❌',
            'event_reminder'                                             => '⏰',
            'event_cancelled', 'event_cancelled_quorum'                  => '🚫',
            'group_invite', 'tournament_team_invite'                     => '🤝',
            'event_invite'                                               => '🏐',
            'organizer_player_registered', 'organizer_registered_player' => '✅',
            'organizer_player_cancelled',  'organizer_cancelled_player'  => '⛔️',
            'organizer_deleted_player'                                   => '🗑',
            'organizer_player_auto_booked'                               => '✅',
            'organizer_player_waitlisted'                                => '🔄',
            'organizer_player_waitlist_left'                             => '❎',
            default                                                      => '📢',
        };

        if ($type === 'organizer_player_waitlisted') {
            return $this->buildOrganizerWaitlistText($payload);
        }

        if ($type === 'organizer_player_waitlist_left') {
            return $this->buildOrganizerWaitlistLeftText($payload);
        }

        // Организаторские уведомления о регистрации — структурированный формат
        $organizerPlayerTypes = [
            'organizer_player_registered', 'organizer_registered_player',
            'organizer_player_cancelled',  'organizer_cancelled_player',
            'organizer_deleted_player',
            'organizer_player_auto_booked',
        ];
        if (in_array($type, $organizerPlayerTypes, true)) {
            return $this->buildOrganizerPlayerText($emoji, $rich['title'], $payload, $type);
        }

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

    private function buildOrganizerPlayerText(string $emoji, string $title, array $payload, string $type): string
    {
        $eventTitle = trim((string) ($payload['event_title'] ?? ''));
        $header = $emoji . ' ' . $title;
        if ($eventTitle !== '') {
            $header .= ' 🏐 ' . $eventTitle;
        }

        $lines = [$header, ''];

        $eventDate    = trim((string) ($payload['event_date'] ?? ''));
        $eventTime    = trim((string) ($payload['event_time'] ?? ''));
        $locationFull = trim((string) ($payload['location_full'] ?? $payload['location_address'] ?? ''));

        $lines[] = 'Информация:';
        if ($eventDate !== '')    $lines[] = '📆: ' . $eventDate;
        if ($eventTime !== '')    $lines[] = '🕘: ' . $eventTime;
        if ($locationFull !== '') $lines[] = '📍: ' . $locationFull;
        $lines[] = '';

        $bookedCount    = trim((string) ($payload['booked_count'] ?? ''));
        $availableCount = trim((string) ($payload['available_count'] ?? ''));
        if ($bookedCount !== '' || $availableCount !== '') {
            $lines[] = "Сейчас {$bookedCount} мест(о) забронировано, а {$availableCount} доступно.";
            $lines[] = '';
        }

        $playerName     = trim((string) ($payload['player_name'] ?? ''));
        $playerPhone    = trim((string) ($payload['player_phone'] ?? ''));
        $playerPosition = trim((string) ($payload['player_position'] ?? ''));

        if ($playerName !== '' || $playerPhone !== '' || $playerPosition !== '') {
            $detailsLabel = $type === 'organizer_player_auto_booked'
                ? 'Детали авто-записи:'
                : 'Детали записи:';
            $lines[] = $detailsLabel;
            $lines[] = '';
            if ($playerName !== '')     $lines[] = '👤 : ' . $playerName;
            if ($playerPhone !== '')    $lines[] = '☎️ : ' . $playerPhone;
            if ($playerPosition !== '') $lines[] = '✅ : ' . $playerPosition;
        }

        $lines[] = '';
        $lines[] = '--';
        $lines[] = config('app.name', 'Volley Club');

        return implode("\n", $lines);
    }

    private function buildOrganizerWaitlistText(array $payload): string
    {
        $date           = trim((string) ($payload['event_date'] ?? ''));
        $time           = trim((string) ($payload['event_time'] ?? ''));
        $title          = trim((string) ($payload['event_title'] ?? ''));
        $playerName     = trim((string) ($payload['player_name'] ?? ''));
        $posLabel       = trim((string) ($payload['pos_label'] ?? 'все позиции'));
        $waitlistCount  = (int) ($payload['waitlist_count'] ?? 0);

        $datePart = implode(' ', array_filter([$date, $time]));

        $text = '🔄 На мероприятие';
        if ($datePart !== '') $text .= " {$datePart}";
        if ($title !== '')    $text .= " «{$title}»";
        if ($playerName !== '') $text .= " записался игрок {$playerName}";
        $text .= " в лист ожидания на позицию: {$posLabel}.";
        $text .= " В списке ожидания {$waitlistCount} " . $this->pluralPlayers($waitlistCount) . '.';

        return $text;
    }

    private function buildOrganizerWaitlistLeftText(array $payload): string
    {
        $date           = trim((string) ($payload['event_date'] ?? ''));
        $time           = trim((string) ($payload['event_time'] ?? ''));
        $title          = trim((string) ($payload['event_title'] ?? ''));
        $playerName     = trim((string) ($payload['player_name'] ?? ''));
        $posLabel       = trim((string) ($payload['pos_label'] ?? 'все позиции'));
        $waitlistCount  = (int) ($payload['waitlist_count'] ?? 0);

        $datePart = implode(' ', array_filter([$date, $time]));

        $text = '❎ Выход из листа ожидания';
        if ($playerName !== '') $text .= ": {$playerName}";
        $text .= ' — мероприятие';
        if ($datePart !== '') $text .= " {$datePart}";
        if ($title !== '')    $text .= " «{$title}»";
        $text .= " (позиции: {$posLabel}).";
        $text .= " В списке ожидания осталось {$waitlistCount} " . $this->pluralPlayers($waitlistCount) . '.';

        return $text;
    }

    private function pluralPlayers(int $n): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n >= 11 && $n <= 19) return 'игроков';
        if ($n1 === 1) return 'игрок';
        if ($n1 >= 2 && $n1 <= 4) return 'игрока';
        return 'игроков';
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
        // Строки-действия с emoji (👉 Открыть ...), в т.ч. после вырезания URL
        $body = preg_replace('/^👉[^\n]*(Открыть|открыть|Принять|открыть страницу)[^\n]*/mu', '', $body ?? '');

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
                'status'        => 'sent',
                'sent_at'       => now(),
                'error'         => null,
                'next_retry_at' => null,
                'updated_at'    => now(),
            ]);
    }

    /**
     * @param bool $isRetry true — эта попытка уже была повтором (из notifications:retry-failed),
     *                       инкрементим attempts. false — исходная попытка, attempts не трогаем
     *                       (иначе "attempts=1" был бы уже на первой же неудаче, до всякого ретрая).
     */
    private function markFailed(int $deliveryId, string $error, bool $isRetry = false): void
    {
        $error = mb_substr($error, 0, 4000);
        $retryable = $this->classifyRetryable($error);

        $attempts = 0;
        if ($isRetry) {
            $attempts = 1 + (int) DB::table('notification_deliveries')->where('id', $deliveryId)->value('attempts');
        }

        // Backoff по количеству УЖЕ сделанных повторов: 0→+1мин, 1→+5мин, 2→+30мин.
        // На attempts=3 (третий повтор тоже упал) дальше не планируем — исчерпано.
        $backoffMinutes = [0 => 1, 1 => 5, 2 => 30][$attempts] ?? null;
        $nextRetryAt = ($retryable && $backoffMinutes !== null) ? now()->addMinutes($backoffMinutes) : null;

        DB::table('notification_deliveries')
            ->where('id', $deliveryId)
            ->update([
                'status'        => 'failed',
                'error'         => $error,
                'is_retryable'  => $retryable,
                'attempts'      => $attempts,
                'next_retry_at' => $nextRetryAt,
                'updated_at'    => now(),
            ]);

        Log::warning('Notification delivery failed', [
            'delivery_id' => $deliveryId,
            'error'       => $error,
            'retryable'   => $retryable,
            'attempts'    => $attempts,
        ]);

        if ($retryable && $backoffMinutes === null) {
            Log::warning('Notification delivery retries exhausted', [
                'delivery_id' => $deliveryId,
                'attempts'    => $attempts,
            ]);
        }

        if (!$retryable) {
            $this->deactivateChannelForDelivery($deliveryId);
        }
    }

    /**
     * Различает транзиентные (сеть — ответ от API не получен вообще) и постоянные
     * (конкретный отказ API: бот заблокирован, чат не существует, аккаунт удалён)
     * ошибки. Транзиентные ретраим, постоянные — нет (жечь лимиты API бессмысленно).
     * Неизвестное — считаем транзиентным (безопаснее дать пару попыток, чем
     * молча похоронить нераспознанную ошибку).
     */
    private function classifyRetryable(string $error): bool
    {
        // cURL error N — сбой на уровне сети/соединения, ответ от API не получен вообще
        if (preg_match('/^cURL error \d+/', $error) === 1) {
            return true;
        }

        // VK возвращает числовой error_code, а не текстовое описание — сигнатуры-подстроки
        // здесь не работают. Коды из документации VK API: 7 (нет прав), 15 (доступ запрещён),
        // 902 (нельзя отправить сообщение этому пользователю) — постоянные. Остальные
        // (6 — rate limit, 10 — внутренняя ошибка и т.п.) считаем транзиентными по умолчанию.
        if (str_contains($error, 'VK API error') && preg_match('/"error_code":\s*(\d+)/', $error, $m) === 1) {
            $vkPermanentCodes = [7, 15, 902];
            if (in_array((int) $m[1], $vkPermanentCodes, true)) {
                return false;
            }
        }

        $permanentSignatures = [
            'chat not found',  // Telegram: "Bad Request: chat not found"
            'chat.not.found',  // MAX: {"code":"chat.not.found",...} — формат с точками, не текст
            "bot can't initiate conversation",
            'user is deactivated',
            'bot was blocked by the user',
            'chat.denied',
            'dialog.suspended',
        ];

        foreach ($permanentSignatures as $signature) {
            if (stripos($error, $signature) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Постоянная ошибка канала — дальнейшие попытки для этого пользователя
     * гарантированно провалятся. Выключаем канал флагом (НЕ трогая сам
     * telegram_id/vk_notify_user_id — они остаются рабочими идентификаторами,
     * бот может быть разблокирован пользователем в любой момент).
     */
    private function deactivateChannelForDelivery(int $deliveryId): void
    {
        $delivery = DB::table('notification_deliveries')->where('id', $deliveryId)->first(['channel', 'user_id']);
        if (!$delivery || !$delivery->user_id) {
            return;
        }

        $column = match ((string) $delivery->channel) {
            'telegram' => 'telegram_notifications_enabled',
            'vk'       => 'vk_notifications_enabled',
            'max'      => 'max_notifications_enabled',
            default    => null,
        };

        if ($column === null) {
            return;
        }

        DB::table('users')->where('id', (int) $delivery->user_id)->update([$column => false]);

        Log::warning('Notification channel deactivated after permanent failure', [
            'user_id' => (int) $delivery->user_id,
            'channel' => $delivery->channel,
        ]);
    }
}