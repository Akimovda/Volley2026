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

    public function send(string $chatId, ChannelMessageData $message): array
    {
        $token = $this->getToken();
        $keyboard = $this->buildKeyboard($message->buttonUrl, $message->buttonText);

        // Отправляем как фото
        if ($message->imageUrl) {
            $caption = $this->truncateCaption($message->text);

            $payload = [
                'chat_id' => $chatId,
                'photo' => $message->imageUrl,
                'parse_mode' => 'HTML',
                'disable_notification' => $message->silent,
            ];

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
        $currentKind = $currentHasImage ? self::MESSAGE_KIND_PHOTO : self::MESSAGE_KIND_TEXT;

        // Если тип сообщения изменился — нельзя просто обновить, нужно удалить и отправить новое
        if ($originalKind && $originalKind !== $currentKind) {
            Log::info('Telegram message kind changed, resending', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'from_kind' => $originalKind,
                'to_kind' => $currentKind,
            ]);

            // Пытаемся удалить старое сообщение
            try {
                $this->getHttpClient()
                    ->post("https://api.telegram.org/bot{$token}/deleteMessage", [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ])
                    ->throw();
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
}