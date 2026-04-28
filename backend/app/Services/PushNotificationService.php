<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;

final class PushNotificationService
{
    private string $keyId;
    private string $teamId;
    private string $bundleId;
    private string $privateKeyPath;
    private bool $production;

    public function __construct()
    {
        $this->keyId          = (string) config('apn.key_id', '');
        $this->teamId         = (string) config('apn.team_id', '');
        $this->bundleId       = (string) config('apn.app_bundle_id', '');
        $this->privateKeyPath = (string) config('apn.private_key_path', '');
        $this->production     = (bool)   config('apn.production', false);
    }

    public function send(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('user_id', $userId)
            ->where('is_active', true)
            ->where('platform', 'ios')
            ->pluck('token');

        foreach ($tokens as $token) {
            try {
                $this->sendToToken($token, $title, $body, $data);
            } catch (\Throwable $e) {
                Log::warning('APNs push failed', [
                    'user_id' => $userId,
                    'token'   => substr($token, 0, 10) . '...',
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendToToken(string $token, string $title, string $body, array $data): void
    {
        if ($this->keyId === '' || $this->teamId === '' || $this->bundleId === '' || $this->privateKeyPath === '') {
            Log::debug('APNs not configured, skipping push notification.');
            return;
        }

        if (!file_exists($this->privateKeyPath)) {
            Log::warning('APNs private key not found: ' . $this->privateKeyPath);
            return;
        }

        $jwt  = $this->buildJwt();
        $host = $this->production
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';

        $payload = json_encode([
            'aps' => [
                'alert' => ['title' => $title, 'body' => $body],
                'sound' => 'default',
            ],
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init("{$host}/3/device/{$token}");
        curl_setopt_array($ch, [
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $jwt,
                'apns-topic: ' . $this->bundleId,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new \RuntimeException('APNs curl error: ' . $curlError);
        }

        if ($httpCode !== 200) {
            // 410 = token no longer valid → deactivate
            if ($httpCode === 410) {
                DeviceToken::where('token', $token)->update(['is_active' => false]);
                return;
            }
            throw new \RuntimeException("APNs HTTP {$httpCode}: {$response}");
        }
    }

    private function buildJwt(): string
    {
        $header  = $this->base64url(json_encode(['alg' => 'ES256', 'kid' => $this->keyId]));
        $payload = $this->base64url(json_encode(['iss' => $this->teamId, 'iat' => time()]));
        $data    = $header . '.' . $payload;

        $privateKey = openssl_pkey_get_private(file_get_contents($this->privateKeyPath));
        if ($privateKey === false) {
            throw new \RuntimeException('APNs: не удалось загрузить приватный ключ.');
        }

        openssl_sign($data, $derSignature, $privateKey, OPENSSL_ALGO_SHA256);

        return $data . '.' . $this->base64url($this->derToJose($derSignature));
    }

    private function derToJose(string $der): string
    {
        $offset = 0;

        if (ord($der[$offset++]) !== 0x30) {
            throw new \RuntimeException('APNs JWT: ожидался DER SEQUENCE');
        }
        $this->derReadLen($der, $offset);

        if (ord($der[$offset++]) !== 0x02) {
            throw new \RuntimeException('APNs JWT: ожидался INTEGER для r');
        }
        $rLen = $this->derReadLen($der, $offset);
        $r    = substr($der, $offset, $rLen);
        $offset += $rLen;

        if (ord($der[$offset++]) !== 0x02) {
            throw new \RuntimeException('APNs JWT: ожидался INTEGER для s');
        }
        $sLen = $this->derReadLen($der, $offset);
        $s    = substr($der, $offset, $sLen);

        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    private function derReadLen(string $der, int &$offset): int
    {
        $len = ord($der[$offset++]);
        if ($len & 0x80) {
            $numBytes = $len & 0x7f;
            $len = 0;
            for ($i = 0; $i < $numBytes; $i++) {
                $len = ($len << 8) | ord($der[$offset++]);
            }
        }
        return $len;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
