<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\UserNotification;
use App\Services\Push\ApnsChannel;
use App\Services\Push\FcmChannel;
use App\Services\Push\PushChannelInterface;
use Illuminate\Support\Facades\Log;

final class PushNotificationService
{
    private PushChannelInterface $apnsChannel;
    private PushChannelInterface $fcmChannel;

    public function __construct(ApnsChannel $apnsChannel, FcmChannel $fcmChannel)
    {
        $this->apnsChannel = $apnsChannel;
        $this->fcmChannel  = $fcmChannel;
    }

    /**
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function send(int $userId, string $title, string $body, array $data = []): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        $tokens = DeviceToken::where('user_id', $userId)
            ->where('is_active', true)
            ->whereIn('platform', ['ios', 'android'])
            ->get();

        Log::debug('Push send() called', [
            'user_id'      => $userId,
            'title'        => $title,
            'tokens_found' => $tokens->count(),
        ]);

        if ($tokens->isEmpty()) {
            Log::debug('Push: no active tokens for user, skipping.', ['user_id' => $userId]);
            return $result;
        }

        $badge = UserNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        foreach ($tokens as $deviceToken) {
            $channel = $deviceToken->platform === 'android' ? $this->fcmChannel : $this->apnsChannel;

            try {
                $sent = $channel->send($deviceToken, $title, $body, $data, $badge);
                if ($sent) {
                    $result['sent']++;
                    Log::info('Push sent', [
                        'user_id'  => $userId,
                        'platform' => $deviceToken->platform,
                        'token'    => substr($deviceToken->token, 0, 10) . '...',
                    ]);
                } else {
                    $result['skipped']++;
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                Log::error('Push failed', [
                    'user_id'  => $userId,
                    'platform' => $deviceToken->platform,
                    'token'    => substr($deviceToken->token, 0, 10) . '...',
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        Log::debug('Push send() finished', array_merge(['user_id' => $userId], $result));

        return $result;
    }
}
