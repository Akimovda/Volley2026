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
        return Http::timeout(15);
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
     * Rich Message (Bot API 10.1+) — коллаж из нескольких фото и/или сворачиваемый
     * details-блок для списка игроков. Схема блоков подтверждена вживую тестовыми
     * вызовами sendRichMessage (2026-08-28), не только по документации.
     */
    private function isRichEligible(ChannelMessageData $message): bool
    {
        return count($message->imageUrls ?? []) > 1 || !empty($message->listText);
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

                if ($message->messageThreadId) {
                    $payload['message_thread_id'] = $message->messageThreadId;
                }

                if ($keyboard) {
                    $payload['reply_markup'] = $keyboard;
                }

                $response = $this->getHttpClient()
                    ->post("https://api.telegram.org/bot{$token}/sendRichMessage", $payload)
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

            if ($message->messageThreadId) {
                $payload['message_thread_id'] = $message->messageThreadId;
            }

            if ($caption) {
                $payload['caption'] = $caption;
            }

            if ($keyboard) {
                $payload['reply_markup'] = $keyboard;
            }

            $response = $this->getHttpClient()
                ->post("https://api.telegram.org/bot{$token}/sendPhoto", $payload)
                ->throw()
                ->json();

            return [
                'external_chat_id' => (string) data_get($response, 'result.chat.id'),
                'external_message_id' => (string) data_get($response, 'result.message_id'),
                'raw' => $response,
                'meta' => [
                    'message_kind' => self::MESSAGE_KIND_PHOTO,
                ],
            ];
        }

        // Отправляем как текст
        $payload = [
            'chat_id' => $chatId,
            'text' => $message->text,
            'parse_mode' => 'HTML',
            'disable_notification' => $message->silent,
        ];

        if ($message->messageThreadId) {
            $payload['message_thread_id'] = $message->messageThreadId;
        }

        if ($keyboard) {
            $payload['reply_markup'] = $keyboard;
        }

        $response = $this->getHttpClient()
            ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload)
            ->throw()
            ->json();

        return [
            'external_chat_id' => (string) data_get($response, 'result.chat.id'),
            'external_message_id' => (string) data_get($response, 'result.message_id'),
            'raw' => $response,
            'meta' => [
                'message_kind' => self::MESSAGE_KIND_TEXT,
            ],
        ];
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
                    ->post("https://api.telegram.org/bot{$token}/editMessageText", $payload)
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
                ->post("https://api.telegram.org/bot{$token}/editMessageCaption", $payload)
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
            ->post("https://api.telegram.org/bot{$token}/editMessageText", $payload)
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
            ->post("https://api.telegram.org/bot{$token}/deleteMessage", [
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