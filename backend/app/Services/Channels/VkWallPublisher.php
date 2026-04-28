<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Data\ChannelMessageData;
use App\Services\Channels\Contracts\ChannelPublisher;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class VkWallPublisher implements ChannelPublisher
{
    private const API_BASE = 'https://api.vk.com/method/';
    private const API_VER  = '5.199';

    public function __construct(private string $accessToken) {}

    public function send(string $chatId, ChannelMessageData $message): array
    {
        // chatId = owner_id (отрицательный для сообщества, напр. -123456)
        $ownerId = (int) $chatId;

        $text = $message->title . "\n\n" . $message->text;
        if ($message->buttonUrl) {
            $text .= "\n\n" . ($message->buttonText ?: 'Подробнее') . ': ' . $message->buttonUrl;
        }

        $params = [
            'owner_id'     => $ownerId,
            'from_group'   => 1,
            'message'      => $text,
            'v'            => self::API_VER,
            'access_token' => $this->accessToken,
        ];

        // Если есть картинка — загружаем
        if ($message->imageUrl) {
            try {
                $attachId = $this->uploadPhoto($ownerId, $message->imageUrl);
                if ($attachId) {
                    $params['attachments'] = $attachId;
                }
            } catch (\Throwable) {
                // продолжаем без картинки
            }
        }

        $response = Http::timeout(20)
            ->post(self::API_BASE . 'wall.post', $params)
            ->json();

        if (isset($response['error'])) {
            throw new RuntimeException(
                'VK wall.post error ' . $response['error']['error_code'] . ': ' . $response['error']['error_msg']
            );
        }

        $postId = $response['response']['post_id'] ?? null;

        return [
            'external_chat_id'    => (string) $ownerId,
            'external_message_id' => $postId ? (string) $postId : null,
            'raw'                 => $response,
            'meta'                => ['kind' => 'vk_wall'],
        ];
    }

    public function update(string $chatId, string $messageId, ChannelMessageData $message, array $previousMeta = []): array
    {
        $ownerId = (int) $chatId;

        $text = $message->title . "\n\n" . $message->text;
        if ($message->buttonUrl) {
            $text .= "\n\n" . ($message->buttonText ?: 'Подробнее') . ': ' . $message->buttonUrl;
        }

        $params = [
            'owner_id'     => $ownerId,
            'post_id'      => (int) $messageId,
            'message'      => $text,
            'v'            => self::API_VER,
            'access_token' => $this->accessToken,
        ];

        $response = Http::timeout(20)
            ->post(self::API_BASE . 'wall.edit', $params)
            ->json();

        if (isset($response['error'])) {
            throw new RuntimeException(
                'VK wall.edit error ' . $response['error']['error_code'] . ': ' . $response['error']['error_msg']
            );
        }

        return [
            'external_chat_id'    => (string) $ownerId,
            'external_message_id' => $messageId,
            'raw'                 => $response,
            'meta'                => ['kind' => 'vk_wall'],
        ];
    }

    public function supportsUpdate(): bool { return true; }
    public function supportsSilent(): bool { return false; }

    private function uploadPhoto(int $ownerId, string $imageUrl): ?string
    {
        // Шаг 1: получить URL для загрузки
        $uploadServer = Http::timeout(10)
            ->get(self::API_BASE . 'photos.getWallUploadServer', [
                'group_id'     => abs($ownerId),
                'v'            => self::API_VER,
                'access_token' => $this->accessToken,
            ])
            ->json();

        if (isset($uploadServer['error']) || empty($uploadServer['response']['upload_url'])) {
            return null;
        }

        $uploadUrl = $uploadServer['response']['upload_url'];

        // Шаг 2: скачиваем картинку
        $imageData = Http::timeout(15)->get($imageUrl)->body();
        if (empty($imageData)) {
            return null;
        }

        // Шаг 3: загружаем на сервер VK
        $uploaded = Http::timeout(30)
            ->attach('photo', $imageData, 'photo.jpg')
            ->post($uploadUrl)
            ->json();

        if (empty($uploaded['server']) || empty($uploaded['photo']) || empty($uploaded['hash'])) {
            return null;
        }

        // Шаг 4: сохраняем фото
        $saved = Http::timeout(10)
            ->post(self::API_BASE . 'photos.saveWallPhoto', [
                'group_id'     => abs($ownerId),
                'server'       => $uploaded['server'],
                'photo'        => $uploaded['photo'],
                'hash'         => $uploaded['hash'],
                'v'            => self::API_VER,
                'access_token' => $this->accessToken,
            ])
            ->json();

        if (empty($saved['response'][0])) {
            return null;
        }

        $photo = $saved['response'][0];

        return 'photo' . $photo['owner_id'] . '_' . $photo['id'];
    }
}
