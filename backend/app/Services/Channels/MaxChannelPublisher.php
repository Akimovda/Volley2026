<?php

namespace App\Services\Channels;

use App\Data\ChannelMessageData;
use App\Services\Channels\Contracts\ChannelPublisher;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class MaxChannelPublisher implements ChannelPublisher
{
    public function __construct(private readonly ?string $customToken = null) {}

    private function endpoint(): string
    {
        // Для персонального бота — прямой API MAX, иначе внутренний сервис
        if ($this->customToken !== null) {
            return 'https://platform-api.max.ru';
        }
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

    private function getToken(): string
    {
        if ($this->customToken !== null && $this->customToken !== '') {
            return $this->customToken;
        }
        $token = (string) config('services.max.bot_token');
        if ($token === '') throw new LogicException('MAX bot_token is not configured.');
        return $token;
    }

    /** Для персонального бота — прямой вызов MAX API */
    private function sendDirect(string $chatId, ChannelMessageData $message): array
    {
        $token = $this->getToken();
        $body  = ['text' => $message->text];

        $attachments = [];
        if ($message->buttonUrl) {
            $attachments[] = [
                'type'    => 'inline_keyboard',
                'payload' => [
                    'buttons' => [[
                        ['type' => 'link', 'text' => $message->buttonText ?: 'Записаться!', 'url' => $message->buttonUrl],
                    ]],
                ],
            ];
        }
        if (!empty($attachments)) {
            $body['attachments'] = $attachments;
        }

        $response = Http::timeout(20)
            ->withHeaders(['Authorization' => $token, 'Content-Type' => 'application/json'])
            ->post("https://platform-api.max.ru/messages?chat_id=" . urlencode($chatId), $body)
            ->throw()
            ->json();

        return [
            'external_chat_id'    => $chatId,
            'external_message_id' => isset($response['message']['id']) ? (string) $response['message']['id'] : null,
            'raw'                 => $response ?? [],
            'meta'                => ['message_kind' => 'text'],
        ];
    }

    public function send(string $chatId, ChannelMessageData $message): array
    {
        // Персональный бот — прямой вызов MAX API
        if ($this->customToken !== null) {
            return $this->sendDirect($chatId, $message);
        }

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

        $attachments = [];

        // Если предыдущее сообщение было с фото — передаём photo_id обратно,
        // чтобы MAX не удалил вложение при редактировании.
        // Ищем сначала в явно сохранённом saved_image_attachment (из предыдущего update),
        // затем в raw ответе бота (из первоначального send).
        $imageAttachment = data_get($previousMeta, 'saved_image_attachment');
        if (!$imageAttachment) {
            $prevAttachments = data_get($previousMeta, 'raw.raw.message.body.attachments', []);
            foreach ($prevAttachments as $att) {
                if (($att['type'] ?? '') === 'image' && isset($att['payload']['photo_id'])) {
                    $imageAttachment = [
                        'type'    => 'image',
                        'payload' => [
                            'photo_id' => $att['payload']['photo_id'],
                            'token'    => $att['payload']['token'] ?? null,
                        ],
                    ];
                    break;
                }
            }
        }

        if ($imageAttachment) {
            $attachments[] = $imageAttachment;
        }

        $hasPhoto = !empty($attachments);

        if ($message->buttonUrl) {
            $attachments[] = [
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
            ];
        }

        if (!empty($attachments)) {
            $body['attachments'] = $attachments;
        }

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => $token,
                'Content-Type'  => 'application/json',
            ])
            ->put('https://platform-api.max.ru/messages?message_id=' . urlencode($messageId), $body)
            ->throw()
            ->json();

        $meta = ['message_kind' => $hasPhoto ? 'photo' : 'text'];
        if ($imageAttachment) {
            // Явно сохраняем вложение, чтобы следующий update тоже мог его передать
            $meta['saved_image_attachment'] = $imageAttachment;
        }

        return [
            'external_chat_id'    => $chatId,
            'external_message_id' => $messageId,
            'raw'                 => $response ?? [],
            'meta'                => $meta,
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