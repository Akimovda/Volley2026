<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CourtBooking;
use App\Models\Event;
use App\Models\PaymentSetting;
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
        $client->setAuth($settings->yoomoney_shop_id, self::platformSecret($settings->yoomoney_secret_key));

        return $client;
    }

    /**
     * Админка платформы сохраняет секретный ключ через encrypt() — для API его нужно расшифровать.
     * Если значение лежит открытым текстом (старые/ручные записи) — отдаём как есть.
     */
    private static function platformSecret(string $stored): string
    {
        try {
            return decrypt($stored);
        } catch (\Throwable) {
            return $stored;
        }
    }

    /**
     * Клиент по ключам КОНКРЕТНОГО организатора/владельца локации (payment_settings),
     * а не платформы — для оплаты аренды корта (Фаза 4) и обычных платных мероприятий.
     */
    private function makeClientFor(PaymentSetting $settings): Client
    {
        if (empty($settings->yoomoney_shop_id) || empty($settings->yoomoney_secret_key)) {
            throw new \RuntimeException('ЮKassa не настроена у организатора: заполните shop_id и secret_key в настройках оплаты.');
        }

        $client = new Client();
        $client->setAuth($settings->yoomoney_shop_id, $settings->yoomoney_secret_key);

        return $client;
    }

    /**
     * Создать реальный платёж ЮKassa за бронь корта, используя ключи владельца локации.
     *
     * @return array{payment_id: string, payment_url: string}
     */
    public function createBookingPayment(CourtBooking $booking, PaymentSetting $settings, string $returnUrl): array
    {
        $client = $this->makeClientFor($settings);
        $idempotenceKey = 'court-booking-' . $booking->id . '-' . Str::random(8);

        $court = $booking->court;
        $description = 'Бронь корта' . ($court ? " «{$court->name}»" : '') . ' #' . $booking->id;

        $response = $client->createPayment([
            'amount' => [
                'value'    => number_format((float) $booking->price_total, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'confirmation' => [
                'type'       => 'redirect',
                'return_url' => $returnUrl,
            ],
            'capture'     => true,
            'description' => $description,
            'metadata'    => [
                'court_booking_id' => $booking->id,
                'type'             => 'court_booking',
            ],
        ], $idempotenceKey);

        return [
            'payment_id'  => $response->getId(),
            'payment_url' => $response->getConfirmation()->getConfirmationUrl(),
        ];
    }

    /**
     * Переспросить статус конкретного платежа напрямую у ЮKassa (не доверять телу вебхука) —
     * тем же способом, что уже используется для рекламных платежей (handleWebhook() ниже).
     */
    public function verifyPayment(string $yooPaymentId, PaymentSetting $settings): ?string
    {
        try {
            $info = $this->makeClientFor($settings)->getPaymentInfo($yooPaymentId);
            return $info?->getStatus();
        } catch (\Throwable $e) {
            Log::error('YookassaService::verifyPayment', ['error' => $e->getMessage(), 'payment_id' => $yooPaymentId]);
            return null;
        }
    }

    /**
     * Настоящий возврат через SDK (в отличие от PaymentService::refund(), который
     * зачисляет на внутренний VirtualWallet) — деньги возвращаются на карту клиента.
     *
     * @return array{refund_id: string, status: string}
     */
    public function createRefund(string $yooPaymentId, int $amountMinor, PaymentSetting $settings, string $reason = ''): array
    {
        $client = $this->makeClientFor($settings);
        $idempotenceKey = 'refund-' . $yooPaymentId . '-' . Str::random(8);

        $response = $client->createRefund([
            'payment_id' => $yooPaymentId,
            'amount'     => [
                'value'    => number_format($amountMinor / 100, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'description' => $reason ?: 'Возврат по отмене брони',
        ], $idempotenceKey);

        return [
            'refund_id' => $response->getId(),
            'status'    => $response->getStatus(),
        ];
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

    /**
     * Платёж платформы (Организатор Pro / Premium) через ЮKassa по ключам из platform_payment_settings.
     * Сохраняет id платежа и ссылку оплаты в payments; metadata.type = org_pro | premium.
     */
    public function createPlatformPayment(\App\Models\Payment $payment, string $type, string $description, string $returnUrl, ?string $email = null): string
    {
        $client = $this->makeClient();
        $settings = PlatformPaymentSetting::first();
        $value = number_format($payment->amount_minor / 100, 2, '.', '');

        $params = [
            'amount'       => ['value' => $value, 'currency' => 'RUB'],
            'confirmation' => ['type' => 'redirect', 'return_url' => $returnUrl],
            'capture'      => true,
            'description'  => $description,
            'metadata'     => ['type' => $type, 'payment_id' => $payment->id],
        ];

        if ($settings?->yookassa_receipt_enabled) {
            $receipt = [
                'customer' => ['email' => $email ?: 'no-reply@volleyplay.club'],
                'items'    => [[
                    'description' => mb_substr($description, 0, 128),
                    'quantity'    => '1.00',
                    'amount'      => ['value' => $value, 'currency' => 'RUB'],
                    'vat_code'    => (int) ($settings->yookassa_vat_code ?: 1),
                    'payment_mode'    => 'full_payment',
                    'payment_subject' => 'service',
                ]],
            ];
            if ($settings->yookassa_tax_system_code) {
                $receipt['tax_system_code'] = (int) $settings->yookassa_tax_system_code;
            }
            $params['receipt'] = $receipt;
        }

        $response = $client->createPayment($params, 'platform-' . $payment->id . '-' . Str::random(8));
        $url = $response->getConfirmation()->getConfirmationUrl();

        $payment->update([
            'yoomoney_payment_id'       => $response->getId(),
            'yoomoney_confirmation_url' => $url,
        ]);

        return $url;
    }

    /**
     * Статус платежа платформы напрямую у ЮKassa (вебхуку и редиректу доверять нельзя).
     */
    public function verifyPlatformPayment(string $yooPaymentId): ?string
    {
        try {
            return $this->makeClient()->getPaymentInfo($yooPaymentId)?->getStatus();
        } catch (\Throwable $e) {
            Log::error('YookassaService::verifyPlatformPayment', ['error' => $e->getMessage(), 'payment_id' => $yooPaymentId]);
            return null;
        }
    }

    /**
     * Для payment_succeeded по Pro/Premium: проверяет платёж в ЮKassa и возвращает наш Payment (или null).
     */
    public function platformPaymentFromWebhook(string $rawBody): ?\App\Models\Payment
    {
        try {
            $data = json_decode($rawBody, true);
            if (($data['event'] ?? '') !== NotificationEventType::PAYMENT_SUCCEEDED) {
                return null;
            }
            $object   = (new NotificationSucceeded($data))->getObject();
            $metadata = $object->getMetadata();
            if (!in_array($metadata['type'] ?? '', ['org_pro', 'premium'], true) || empty($metadata['payment_id'])) {
                return null;
            }
            $payment = \App\Models\Payment::find((int) $metadata['payment_id']);
            if (!$payment || $payment->yoomoney_payment_id !== $object->getId()) {
                return null;
            }
            return $this->verifyPlatformPayment($object->getId()) === 'succeeded' ? $payment : null;
        } catch (\Throwable $e) {
            Log::error('YookassaService::platformPaymentFromWebhook', ['error' => $e->getMessage()]);
            return null;
        }
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
