<?php

namespace App\Services\Channels;

use App\Data\ChannelMessageData;
use App\Services\Channels\Contracts\ChannelPublisher;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class VkChannelPublisher implements ChannelPublisher
{
    public function send(string $chatId, ChannelMessageData $message): array
    {
        $endpoint = rtrim((string) config('services.vk.bot_api_url'), '/');
        $secret = (string) config('services.bind.secret');

        if ($endpoint === '') {
            throw new LogicException('VK bot_api_url is not configured.');
        }

        if ($secret === '') {
            throw new LogicException('Bind secret is not configured.');
        }

        $httpResponse = Http::timeout(15)
            ->withHeaders([
                'X-Bind-Secret' => $secret,
                'Accept' => 'application/json',
            ])
            ->post($endpoint . '/send', [
                'chat_id' => $chatId,
                'text' => $message->text,
                'button_url' => $message->buttonUrl,
                'button_text' => $message->buttonText,
                'image_url' => $message->imageUrl,
            ])
            ->throw();

        $response = $httpResponse->json();

        if (!is_array($response)) {
            throw new RuntimeException('VK bot returned invalid JSON.');
        }

        if (empty($response['ok'])) {
            throw new RuntimeException(
                (string) ($response['message'] ?? 'VK bot send failed.')
            );
        }

        return [
            'external_chat_id' => (string) ($response['chat_id'] ?? $chatId),
            'external_message_id' => isset($response['message_id'])
                ? (string) $response['message_id']
                : null,
            'raw' => $response,
            'meta' => [
                'message_kind' => !empty($message->imageUrl) ? 'photo' : 'text',
            ],
        ];
    }

    public function update(
        string $chatId,
        string $messageId,
        ChannelMessageData $message,
        array $previousMeta = []
    ): array {
        throw new LogicException('VK update is not implemented yet.');
    }

    public function supportsUpdate(): bool
    {
        return false;
    }

    public function supportsSilent(): bool
    {
        return false;
    }
}