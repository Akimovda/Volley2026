<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\PlatformPaymentSetting;
use YooKassa\Client;
use YooKassa\Model\Notification\NotificationSucceeded;
use YooKassa\Model\NotificationEventType;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class YookassaService
{
    private function makeClient(): Client
    {
        $settings = PlatformPaymentSetting::first();

        if (!$settings || empty($settings->yoomoney_shop_id) || empty($settings->yoomoney_secret_key)) {
            throw new \RuntimeException('ЮKassa не настроена: заполните shop_id и secret_key в настройках платформы.');
        }

        $client = new Client();
        $client->setAuth($settings->yoomoney_shop_id, $settings->yoomoney_secret_key);

        return $client;
    }

    public function createAdPayment(Event $event): array
    {
        $client         = $this->makeClient();
        $idempotenceKey = 'ad-event-' . $event->id . '-' . Str::random(8);

        $response = $client->createPayment([
            'amount' => [
                'value'    => number_format((float) $event->ad_price_rub, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'confirmation' => [
                'type'       => 'redirect',
                'return_url' => route('events.show', $event->id),
            ],
            'capture'     => true,
            'description' => 'Рекламное мероприятие #' . $event->id . ': ' . $event->title,
            'metadata'    => [
                'event_id' => $event->id,
                'type'     => 'ad_event',
            ],
        ], $idempotenceKey);

        return [
            'payment_id'  => $response->getId(),
            'payment_url' => $response->getConfirmation()->getConfirmationUrl(),
        ];
    }

    public function handleWebhook(string $rawBody): ?int
    {
        try {
            $data = json_decode($rawBody, true);

            if (($data['event'] ?? '') !== NotificationEventType::PAYMENT_SUCCEEDED) {
                return null;
            }

            $notification = new NotificationSucceeded($data);
            $payment      = $notification->getObject();
            $metadata     = $payment->getMetadata();

            if (($metadata['type'] ?? '') !== 'ad_event' || empty($metadata['event_id'])) {
                return null;
            }

            // Верификация через API
            $verified = $this->makeClient()->getPaymentInfo($payment->getId());
            if ($verified->getStatus() !== 'succeeded') {
                return null;
            }

            return (int) $metadata['event_id'];

        } catch (\Throwable $e) {
            Log::error('YookassaService::handleWebhook', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
