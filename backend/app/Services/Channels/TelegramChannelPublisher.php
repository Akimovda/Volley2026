<?php

namespace App\Services\Channels;

use App\Data\ChannelMessageData;
use App\Services\Channels\Contracts\ChannelPublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannelPublisher implements ChannelPublisher
{
    private const MAX_CAPTION_LENGTH = 1024;
    private const MESSAGE_KIND_TEXT  = 'text';
    private const MESSAGE_KIND_PHOTO = 'photo';
    private const MESSAGE_KIND_RICH  = 'rich';

    public function __construct(private readonly ?string $customToken = null) {}

    private function getToken(): string
    {
        if ($this->customToken !== null && $this->customToken !== '') {
            return $this->customToken;
        }

        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            throw new \LogicException('Telegram bot token is not configured.');
        }

        return $token;
    }

    /**
     * Получить HTTP клиент с таймаутом
     */
    private function getHttpClient()
    {
        return \App\Services\TelegramApi::http(15);
    }

    /**
     * Обрезать caption для photo сообщения, если нужно
     */
    private function truncateCaption(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (mb_strlen($text) > self::MAX_CAPTION_LENGTH) {
            return mb_substr($text, 0, self::MAX_CAPTION_LENGTH - 3) . '...';
        }

        return $text;
    }

    /**
     * Построить клавиатуру, если есть кнопка
     */
    private function buildKeyboard(?string $buttonUrl, ?string $buttonText): ?array
    {
        if ($buttonUrl && $buttonText) {
            return [
                'inline_keyboard' => [
                    [
                        ['text' => $buttonText, 'url' => $buttonUrl],
                    ],
                ],
            ];
        }

        return null;
    }

    /**
     * ОТКЛЮЧЕНО 2026-09-09: реальная схема Bot API 10.1 sendRichMessage/rich_message —
     * InputRichMessage.blocks из документированных классов (RichBlockParagraph,
     * RichBlockPhoto, RichBlockCollage и т.д.), где текстовые узлы — структурированный
     * rich-текст (RichTextBold/...), а НЕ голая строка; сворачиваемого блока с полями
     * summary/is_open (как мы его отправляли для списка игроков) в документации вообще
     * нет — ближайший реальный класс RichBlockExpandableBlockQuotation имеет другую
     * форму. Наша реализация ("Rich Messages", 2026-08-28) собирала кастомную JSON-схему
     * (похожую на формат MAX-мессенджера, не на реальный Telegram), которую API
     * принимало (ok:true, эхо на неизвестные поля), но клиенты не могли полностью
     * распарсить — на бою это обрывало видимый текст анонса на полуслове и прятало
     * список игроков (найдено 2026-09-09 по жалобе на анонс occurrence 12514, где
     * реально было 14 записавшихся, а видимый текст обрывался после адреса/организатора).
     * Возвращаем false — публикация идёт по проверенному classic sendPhoto-пути
     * (список игроков — в подписи, с truncateCaption по 1024 символам, как раньше).
     */
    private function isRichEligible(ChannelMessageData $message): bool
    {
        return false;
    }

    private function resolveMessageKind(ChannelMessageData $message): string
    {
        if ($this->isRichEligible($message)) {
            return self::MESSAGE_KIND_RICH;
        }

        return $message->imageUrl ? self::MESSAGE_KIND_PHOTO : self::MESSAGE_KIND_TEXT;
    }

    private function buildRichMessagePayload(ChannelMessageData $message): array
    {
        $blocks = [];

        $imageUrls = $message->imageUrls ?? [];
        if (count($imageUrls) > 1) {
            $blocks[] = [
                'type' => 'collage',
                'blocks' => array_map(
                    fn (string $url) => ['type' => 'photo', 'photo' => ['type' => 'photo', 'media' => $url]],
                    $imageUrls
                ),
            ];
        } elseif (!empty($message->imageUrl)) {
            $blocks[] = ['type' => 'photo', 'photo' => ['type' => 'photo', 'media' => $message->imageUrl]];
        }

        // Rich-параграф не парсит HTML (в отличие от parse_mode=HTML у classic sendPhoto/sendMessage) —
        // заголовок оформляем отдельным bold-узлом, а не склеенным <b>...</b> из $textShort.
        // Builder всегда кладёт "<b>{title}</b>\n\n" первыми двумя строками для platform=telegram
        // (единственная платформа, доходящая до этого класса) — отрезаем этот префикс.
        $bodyText   = $message->textShort ?? $message->text;
        $boldPrefix = "<b>{$message->title}</b>\n\n";
        if (str_starts_with($bodyText, $boldPrefix)) {
            $bodyText = substr($bodyText, strlen($boldPrefix));
        }

        $blocks[] = ['type' => 'paragraph', 'text' => ['type' => 'bold', 'text' => $message->title]];

        if ($bodyText !== '') {
            $blocks[] = ['type' => 'paragraph', 'text' => $bodyText];
        }

        if (!empty($message->listText)) {
            $blocks[] = [
                'type' => 'details',
                'summary' => $message->listTitle ?: 'Подробнее',
                'blocks' => [
                    ['type' => 'paragraph', 'text' => $message->listText],
                ],
                'is_open' => false,
            ];
        }

        return ['blocks' => $blocks];
    }

    public function send(string $chatId, ChannelMessageData $message): array
    {
        $token = $this->getToken();
        $keyboard = $this->buildKeyboard($message->buttonUrl, $message->buttonText);

        // Rich Message — коллаж/сворачиваемый список. При ЛЮБОЙ ошибке (сеть,
        // неожиданный формат ответа) откатываемся на классический sendPhoto ниже —
        // анонс не должен пропасть из-за сбоя нового формата.
        if ($this->isRichEligible($message)) {
            try {
                $payload = [
                    'chat_id' => $chatId,
                    'rich_message' => $this->buildRichMessagePayload($message),
                    'disable_notification' => $message->silent,
                ];

                // главная тема форума (№1) и «не задано» — параметр не передаём (иначе Telegram: «message thread not found»)
                if ($thread = \App\Support\TelegramTopic::normalize($message->messageThreadId)) {
                    $payload['message_thread_id'] = $thread;
                }

                if ($keyboard) {
                    $payload['reply_markup'] = $keyboard;
                }

                $response = $this->getHttpClient()
                    ->post(\App\Services\TelegramApi::url($token, 'sendRichMessage'), $payload)
                    ->throw()
                    ->json();

                return [
                    'external_chat_id' => (string) data_get($response, 'result.chat.id'),
                    'external_message_id' => (string) data_get($response, 'result.message_id'),
                    'raw' => $response,
                    'meta' => [
                        'message_kind' => self::MESSAGE_KIND_RICH,
                    ],
                ];
            } catch (\Throwable $e) {
                Log::warning('TelegramChannelPublisher: sendRichMessage failed, falling back to sendPhoto', [
                    'chat_id' => $chatId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // Отправляем как фото
        if ($message->imageUrl) {
            $caption = $this->truncateCaption($message->text);

            $payload = [
                'chat_id' => $chatId,
                'photo' => $message->imageUrl,
                'parse_mode' => 'HTML',
                'disable_notification' => $message->silent,
            ];

            // главная тема форума (№1) и «не задано» — параметр не передаём (иначе Telegram: «message thread not found»)
            if ($thread = \App\Support\TelegramTopic::normalize($message->messageThreadId)) {
                $payload['message_thread_id'] = $thread;
            }

            if ($caption) {
                $payload['caption'] = $caption;
            }

            if ($keyboard) {
                $payload['reply_markup'] = $keyboard;
            }

            $fellBackFrom = null;
            try {
                $response = $this->postWithThreadFallback($token, 'sendPhoto', $payload, $fellBackFrom);
            } catch (\Illuminate\Http\Client\RequestException $e) {
                // Telegram не смог скачать картинку по ссылке (кэш DNS на старый адрес сервера, недоступный хостинг картинок и т.п.) —
                // загружаем файл сами; нет локального файла — публикуем анонс текстом без картинки, но не теряем его
                if (!$this->isPhotoFetchError($e)) {
                    throw $e;
                }
                $response = $this->sendPhotoByUpload($token, $payload, (string) $message->imageUrl, $fellBackFrom);
                if ($response === null) {
                    return $this->sendTextOnly($token, $chatId, $message, $keyboard);
                }
            }

            return [
                'external_chat_id' => (string) data_get($response, 'result.chat.id'),
                'external_message_id' => (string) data_get($response, 'result.message_id'),
                'raw' => $response,
                'meta' => array_filter([
                    'message_kind' => self::MESSAGE_KIND_PHOTO,
                    'thread_fallback_from' => $fellBackFrom,
                ], fn ($v) => $v !== null),
            ];
        }

        // Отправляем как текст
        $payload = [
            'chat_id' => $chatId,
            'text' => $message->text,
            'parse_mode' => 'HTML',
            'disable_notification' => $message->silent,
        ];

        // главная тема форума (№1) и «не задано» — параметр не передаём (иначе Telegram: «message thread not found»)
        if ($thread = \App\Support\TelegramTopic::normalize($message->messageThreadId)) {
            $payload['message_thread_id'] = $thread;
        }

        if ($keyboard) {
            $payload['reply_markup'] = $keyboard;
        }

        $fellBackFrom = null;
        $response = $this->postWithThreadFallback($token, 'sendMessage', $payload, $fellBackFrom);

        return [
            'external_chat_id' => (string) data_get($response, 'result.chat.id'),
            'external_message_id' => (string) data_get($response, 'result.message_id'),
            'raw' => $response,
            'meta' => array_filter([
                'message_kind' => self::MESSAGE_KIND_TEXT,
                'thread_fallback_from' => $fellBackFrom,
            ], fn ($v) => $v !== null),
        ];
    }

    private function isPhotoFetchError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'failed to get http url content')
            || str_contains($msg, 'wrong file identifier')
            || str_contains($msg, 'wrong type of the web page content')
            || str_contains($msg, 'wrong remote file identifier');
    }

    /** Локальный файл по URL нашего сайта (/storage/…, /img/…) — иначе null. */
    private function localPathFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $host = (string) parse_url($url, PHP_URL_HOST);
        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($path === '' || ($host !== '' && $appHost !== '' && !str_ends_with($host, $appHost))) {
            return null;
        }
        $file = public_path(ltrim(rawurldecode($path), '/'));
        return is_file($file) ? $file : null;
    }

    /** sendPhoto файлом (multipart). null — файла нет локально. С тем же запасным вариантом для неверной темы. */
    private function sendPhotoByUpload(string $token, array $payload, string $imageUrl, ?int &$fellBackFrom): ?array
    {
        $file = $this->localPathFromUrl($imageUrl);
        if ($file === null) {
            return null;
        }
        $fields = [];
        foreach ($payload as $k => $v) {
            if ($k === 'photo') {
                continue;
            }
            $fields[$k] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
        }
        $url  = \App\Services\TelegramApi::url($token, 'sendPhoto');
        $send = fn (array $f) => \App\Services\TelegramApi::http(30)
            ->attach('photo', file_get_contents($file), basename($file))
            ->post($url, $f);

        Log::warning('TelegramChannelPublisher: картинка не скачалась по ссылке — загружаем файлом', ['url' => $imageUrl]);
        $resp = $send($fields);

        if ($resp->status() === 400 && !empty($fields['message_thread_id'])) {
            $desc = strtolower((string) data_get($resp->json(), 'description', ''));
            if (str_contains($desc, 'thread not found') || str_contains($desc, 'topic_id_invalid') || str_contains($desc, 'topic_closed') || str_contains($desc, 'topic closed')) {
                $fellBackFrom = (int) $fields['message_thread_id'];
                unset($fields['message_thread_id']);
                $resp = $send($fields);
            }
        }

        return $resp->throw()->json();
    }

    /** Анонс текстом (без картинки) — когда картинку невозможно ни скачать по ссылке, ни загрузить. */
    private function sendTextOnly(string $token, string $chatId, ChannelMessageData $message, ?array $keyboard): array
    {
        Log::warning('TelegramChannelPublisher: картинка недоступна — публикуем анонс без неё', ['chat_id' => $chatId]);
        $payload = [
            'chat_id' => $chatId,
            'text' => $message->text,
            'parse_mode' => 'HTML',
            'disable_notification' => $message->silent,
        ];
        if ($thread = \App\Support\TelegramTopic::normalize($message->messageThreadId)) {
            $payload['message_thread_id'] = $thread;
        }
        if ($keyboard) {
            $payload['reply_markup'] = $keyboard;
        }
        $fellBackFrom = null;
        $response = $this->postWithThreadFallback($token, 'sendMessage', $payload, $fellBackFrom);

        return [
            'external_chat_id' => (string) data_get($response, 'result.chat.id'),
            'external_message_id' => (string) data_get($response, 'result.message_id'),
            'raw' => $response,
            'meta' => array_filter([
                'message_kind' => self::MESSAGE_KIND_TEXT,
                'thread_fallback_from' => $fellBackFrom,
                'photo_fallback' => 'text_only',
            ], fn ($v) => $v !== null),
        ];
    }

    /**
     * POST в Bot API с запасным вариантом для неверной темы: если Telegram отвечает «message thread not found» /
     * TOPIC_ID_INVALID / TOPIC_CLOSED (тему удалили, закрыли или номер указан неверно) — публикуем БЕЗ темы
     * (в главную), чтобы анонс не пропал. $fellBackFrom получает номер темы, от которой отказались.
     */
    private function postWithThreadFallback(string $token, string $method, array $payload, ?int &$fellBackFrom): array
    {
        $url  = \App\Services\TelegramApi::url($token, $method);
        $resp = $this->getHttpClient()->post($url, $payload);

        if ($resp->status() === 400 && !empty($payload['message_thread_id'])) {
            $desc = strtolower((string) data_get($resp->json(), 'description', ''));
            if (str_contains($desc, 'thread not found') || str_contains($desc, 'topic_id_invalid') || str_contains($desc, 'topic_closed') || str_contains($desc, 'topic closed')) {
                $fellBackFrom = (int) $payload['message_thread_id'];
                Log::warning('TelegramChannelPublisher: тема недоступна, публикуем без темы (в главную)', [
                    'chat_id' => $payload['chat_id'] ?? null,
                    'thread'  => $fellBackFrom,
                    'error'   => $desc,
                ]);
                unset($payload['message_thread_id']);
                $resp = $this->getHttpClient()->post($url, $payload);
            }
        }

        return $resp->throw()->json();
    }

    public function update(string $chatId, string $messageId, ChannelMessageData $message, array $previousMeta = []): array
    {
        $token = $this->getToken();
        $keyboard = $this->buildKeyboard($message->buttonUrl, $message->buttonText);

        // Определяем исходный тип сообщения из meta
        $originalKind = $previousMeta['message_kind'] ?? null;
        $currentHasImage = !empty($message->imageUrl);
        $currentKind = $this->resolveMessageKind($message);

        // Rich↔rich: редактируем через editMessageText с rich_message (не text) —
        // text/rich_message взаимоисключающие параметры editMessageText.
        if ($originalKind === self::MESSAGE_KIND_RICH && $currentKind === self::MESSAGE_KIND_RICH) {
            try {
                $payload = [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'rich_message' => $this->buildRichMessagePayload($message),
                ];

                if ($keyboard) {
                    $payload['reply_markup'] = $keyboard;
                }

                $response = $this->getHttpClient()
                    ->post(\App\Services\TelegramApi::url($token, 'editMessageText'), $payload)
                    ->throw()
                    ->json();

                return [
                    'raw' => $response,
                    'meta' => [
                        'message_kind' => self::MESSAGE_KIND_RICH,
                    ],
                ];
            } catch (\Throwable $e) {
                Log::warning('TelegramChannelPublisher: rich update failed, resending', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
                // Падаем в общий путь смены kind ниже (delete+resend)
                $originalKind = 'rich_failed';
            }
        }

        // Если тип сообщения изменился — нельзя просто обновить, нужно удалить и отправить новое.
        // currentKind===rich всегда сюда, если не обработан выше — editMessageCaption/editMessageText
        // ниже не умеют превращать photo/text в rich, даже если originalKind неизвестен (null).
        if (($originalKind && $originalKind !== $currentKind) || $currentKind === self::MESSAGE_KIND_RICH) {
            Log::info('Telegram message kind changed, resending', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'from_kind' => $originalKind,
                'to_kind' => $currentKind,
            ]);

            // Пытаемся удалить старое сообщение
            try {
                $this->delete($chatId, $messageId);
            } catch (\Exception $e) {
                Log::warning('Failed to delete old message before resend', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Отправляем новое сообщение
            return $this->send($chatId, $message);
        }

        // Тип не изменился — обновляем существующее сообщение
        if ($currentHasImage) {
            // Обновляем фото-сообщение
            $caption = $this->truncateCaption($message->text);

            $payload = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'parse_mode' => 'HTML',
            ];

            if ($caption) {
                $payload['caption'] = $caption;
            } else {
                // Telegram не позволяет убрать caption совсем, передаем пустую строку
                $payload['caption'] = '';
            }

            if ($keyboard) {
                $payload['reply_markup'] = $keyboard;
            }

            $response = $this->getHttpClient()
                ->post(\App\Services\TelegramApi::url($token, 'editMessageCaption'), $payload)
                ->throw()
                ->json();

            return [
                'raw' => $response,
                'meta' => [
                    'message_kind' => self::MESSAGE_KIND_PHOTO,
                ],
            ];
        }

        // Обновляем текстовое сообщение
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message->text,
            'parse_mode' => 'HTML',
        ];

        if ($keyboard) {
            $payload['reply_markup'] = $keyboard;
        }

        $response = $this->getHttpClient()
            ->post(\App\Services\TelegramApi::url($token, 'editMessageText'), $payload)
            ->throw()
            ->json();

        return [
            'raw' => $response,
            'meta' => [
                'message_kind' => self::MESSAGE_KIND_TEXT,
            ],
        ];
    }

    public function supportsUpdate(): bool
    {
        return true;
    }

    public function supportsSilent(): bool
    {
        return true;
    }

    public function delete(string $chatId, string $messageId): bool
    {
        $token = $this->getToken();

        $response = $this->getHttpClient()
            ->post(\App\Services\TelegramApi::url($token, 'deleteMessage'), [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ])
            ->throw()
            ->json();

        return (bool) ($response['ok'] ?? false);
    }

    public function supportsDelete(): bool
    {
        return true;
    }
}