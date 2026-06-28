<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class FcmChannel implements PushChannelInterface
{
    private string $projectId;
    private string $serviceAccountPath;

    public function __construct()
    {
        $this->projectId          = (string) config('fcm.project_id', '');
        $this->serviceAccountPath = (string) config('fcm.service_account_path', '');
    }

    public function send(DeviceToken $deviceToken, string $title, string $body, array $data, int $badge): bool
    {
        if ($this->projectId === '' || !file_exists($this->serviceAccountPath)) {
            Log::warning('FCM not configured, skipping push notification.', [
                'project_id'  => $this->projectId,
                'key_exists'  => file_exists($this->serviceAccountPath),
            ]);
            return false;
        }

        $token       = $deviceToken->token;
        $accessToken = $this->getAccessToken();

        $payload = json_encode([
            'message' => [
                'token'        => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data'         => array_map('strval', array_filter($data, fn($v) => is_scalar($v))),
                'android'      => ['priority' => 'high'],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new \RuntimeException('FCM curl error: ' . $curlError);
        }

        Log::debug('FCM HTTP response', [
            'token'    => substr($token, 0, 10) . '...',
            'httpCode' => $httpCode,
            'response' => $response ?: '(empty)',
        ]);

        if ($httpCode === 200) {
            return true;
        }

        $responseBody = json_decode($response, true);
        $status       = $responseBody['error']['status'] ?? '';

        // Токен устарел или невалиден — деактивируем
        if ($httpCode === 404 || ($httpCode === 400 && $status === 'INVALID_ARGUMENT')) {
            $deviceToken->update(['is_active' => false]);
            Log::warning("FCM token deactivated ({$httpCode} {$status})", ['token' => substr($token, 0, 10) . '...']);
            return false;
        }

        // OAuth2 токен истёк — сбрасываем кеш (следующий вызов получит новый)
        if ($httpCode === 401) {
            Cache::forget('fcm_access_token');
            Log::warning('FCM 401: access token expired, cache cleared.');
        }

        throw new \RuntimeException("FCM HTTP {$httpCode}: {$response}");
    }

    private function getAccessToken(): string
    {
        return Cache::remember('fcm_access_token', 3540, function () {
            $sa  = json_decode(file_get_contents($this->serviceAccountPath), true);
            $jwt = $this->buildOauth2Jwt($sa);

            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);

            $response  = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError !== '') {
                throw new \RuntimeException('FCM OAuth2 curl error: ' . $curlError);
            }

            $data = json_decode($response, true);

            if (empty($data['access_token'])) {
                throw new \RuntimeException('FCM OAuth2 failed: ' . $response);
            }

            return $data['access_token'];
        });
    }

    private function buildOauth2Jwt(array $sa): string
    {
        $now = time();

        $header  = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64url(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $data       = $header . '.' . $payload;
        $privateKey = openssl_pkey_get_private($sa['private_key']);

        if ($privateKey === false) {
            throw new \RuntimeException('FCM: не удалось загрузить приватный ключ.');
        }

        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $data . '.' . $this->base64url($signature);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
