<?php

namespace App\Services\Channels;

use App\Data\ChannelMessageData;
use App\Services\Channels\Contracts\ChannelPublisher;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class MaxChannelPublisher implements ChannelPublisher
{
    private function endpoint(): string
    {
        $url = rtrim((string) config('services.max.bot_api_url'), '/');
        if ($url === '') throw new LogicException('MAX bot_api_url is not configured.');
        return $url;
    }

    private function secret(): string
    {
        $s = (string) config('services.bind.secret');
        if ($s === '') throw new LogicException('Bind secret is not configured.');
        return $s;
    }

    public function send(string $chatId, ChannelMessageData $message): array
    {
        $response = Http::timeout(20)
            ->withHeaders([
                'X-Bind-Secret' => $this->secret(),
                'Accept'        => 'application/json',
            ])
            ->post($this->endpoint() . '/send', [
                'chat_id'     => $chatId,
                'text'        => $message->text,
                'button_url'  => $message->buttonUrl,
                'button_text' => $message->buttonText,
                'image_url'   => $message->imageUrl,
                'silent'      => $message->silent,
            ])
            ->throw()
            ->json();

        if (!is_array($response)) throw new RuntimeException('MAX bot returned invalid JSON.');
        if (empty($response['ok'])) throw new RuntimeException((string) ($response['message'] ?? 'MAX bot send failed.'));

        return [
            'external_chat_id'    => (string) ($response['chat_id'] ?? $chatId),
            'external_message_id' => isset($response['message_id']) ? (string) $response['message_id'] : null,
            'raw'                 => $response,
            'meta'                => ['message_kind' => !empty($message->imageUrl) ? 'photo' : 'text'],
        ];
    }

    public function update(
        string $chatId,
        string $messageId,
        ChannelMessageData $message,
        array $previousMeta = []
    ): array {
        // MAX API: PUT https://platform-api.max.ru/messages?message_id=...
        // Редактирование доступно для сообщений младше 24 часов
        $token = (string) config('services.max.bot_token');
        if ($token === '') throw new LogicException('MAX bot_token is not configured.');

        $body = ['text' => $message->text];

        // Добавляем inline кнопку если есть
        if ($message->buttonUrl) {
            $body['attachments'] = [[
                'type'    => 'inline_keyboard',
                'payload' => [
                    'buttons' => [[
                        [
                            'type' => 'link',
                            'text' => $message->buttonText ?: 'Записаться!',
                            'url'  => $message->buttonUrl,
                        ],
                    ]],
                ],
            ]];
        }

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => $token,
                'Content-Type'  => 'application/json',
            ])
            ->put('https://platform-api.max.ru/messages?message_id=' . urlencode($messageId), $body)
            ->throw()
            ->json();

        return [
            'external_chat_id'    => $chatId,
            'external_message_id' => $messageId, // остаётся тем же
            'raw'                 => $response ?? [],
            'meta'                => ['message_kind' => 'text'],
        ];
    }

    public function supportsUpdate(): bool
    {
        return true; // ← теперь true
    }

    public function supportsSilent(): bool
    {
        return false;
    }
}