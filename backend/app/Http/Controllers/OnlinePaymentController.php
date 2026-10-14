<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\PlatformPaymentSetting;
use App\Services\PlatformPaymentService;
use App\Services\YookassaService;
use App\Support\PaymentPricing as Pricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Выбор способа онлайн-оплаты, когда комиссия ЮKassa добавляется сверху (она зависит от способа).
 * Три сценария: платёж за мероприятие (ключи организатора), Pro/Premium и реклама (ключи платформы).
 */
class OnlinePaymentController extends Controller
{
    public function __construct(
        private YookassaService $yookassa,
        private PlatformPaymentService $platform,
    ) {}

    // ───────── Платежи (мероприятие / Pro / Premium) ─────────

    public function choose(Request $request, Payment $payment)
    {
        [$settings, $prefix, $base] = $this->paymentContext($request, $payment);

        return view('payment.choose_method', [
            'title'   => $this->title($payment),
            'base'    => $base,
            'options' => $this->options($base, $settings, $prefix),
            'taxPercent' => $this->taxPercent($settings, $prefix),
            'action'  => route('payments.online.start', $payment),
        ]);
    }

    public function start(Request $request, Payment $payment)
    {
        [$settings, $prefix, $base] = $this->paymentContext($request, $payment);

        $method = (string) $request->input('method');
        abort_unless(isset(Pricing::methodPercents($settings, $prefix)[$method]), 422);

        $priced = Pricing::apply($base, $settings, $prefix, $method);
        $payment->update([
            'amount_minor' => $priced['total'],
            'fee_minor'    => $priced['fee'],
            'tax_minor'    => $priced['tax'],
        ]);

        try {
            if ($payment->organizer_id === null) {
                $type = $this->platform->typeOf($payment);
                $url  = $this->yookassa->createPlatformPayment(
                    $payment, $type, ($type === 'org_pro' ? 'Организатор Pro' : 'Premium') . ' #' . $payment->id,
                    route('platform.payment.return', $payment), $request->user()->email, $method,
                );
            } else {
                $event = Event::findOrFail($payment->event_id);
                $back  = $payment->team_id
                    ? route('tournamentTeams.show', [$event, $payment->team_id])
                    : route('events.show', $event->id) . '?occurrence=' . $payment->occurrence_id;
                $url   = $this->yookassa->createEventPayment($payment, $event, $settings, $back, $method);
            }
        } catch (\Throwable $e) {
            Log::error('OnlinePaymentController::start', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Не удалось создать платёж этим способом. Выберите другой или попробуйте позже.');
        }

        return redirect()->away($url);
    }

    // ───────── Реклама ─────────

    public function adChoose(Request $request, Event $event)
    {
        $settings = $this->adContext($request, $event);

        return view('payment.choose_method', [
            'title'   => 'Рекламное мероприятие #' . $event->id,
            'base'    => (int) $event->ad_price_rub * 100,
            'options' => $this->options((int) $event->ad_price_rub * 100, $settings, 'ad_'),
            'taxPercent' => $this->taxPercent($settings, 'ad_'),
            'action'  => route('ads.pay.start', $event),
        ]);
    }

    public function adStart(Request $request, Event $event)
    {
        $settings = $this->adContext($request, $event);

        $method = (string) $request->input('method');
        abort_unless(isset(Pricing::methodPercents($settings, 'ad_')[$method]), 422);

        try {
            $payment = $this->yookassa->createAdPayment($event, $method);
        } catch (\Throwable $e) {
            Log::error('OnlinePaymentController::adStart', ['event_id' => $event->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Не удалось создать платёж этим способом. Выберите другой или попробуйте позже.');
        }

        $event->update([
            'ad_yookassa_payment_id' => $payment['payment_id'],
            // страница выбора остаётся «ссылкой на оплату» события, чтобы можно было выбрать способ заново
        ]);

        return redirect()->away($payment['payment_url']);
    }

    /** Организатор выбрал способ оплаты рекламы (когда у платформы включён выбор). */
    public function adMethod(Request $request, Event $event)
    {
        abort_if((int) $event->organizer_id !== (int) $request->user()->id || $event->ad_payment_status !== 'pending', 403);

        $settings = PlatformPaymentSetting::first();
        $method   = (string) $request->input('payment_method');
        abort_unless($settings && in_array($method, $settings->offeredMethods(), true), 422);

        $event->update(['ad_payment_method' => $method]);

        if ($method === 'yoomoney') {
            if (Pricing::needsChoice($settings, 'ad_')) {
                $url = route('ads.pay.choose', $event);
                $event->update(['ad_yookassa_payment_url' => $url]);
                return redirect()->away($url);
            }
            try {
                $payment = $this->yookassa->createAdPayment($event);
                $event->update([
                    'ad_yookassa_payment_id'  => $payment['payment_id'],
                    'ad_yookassa_payment_url' => $payment['payment_url'],
                ]);
                return redirect()->away($payment['payment_url']);
            } catch (\Throwable $e) {
                Log::error('OnlinePaymentController::adMethod', ['event_id' => $event->id, 'error' => $e->getMessage()]);
                return redirect()->route('events.show', $event)->with('error', 'Не удалось создать платёж. Попробуйте позже.');
            }
        }

        return redirect()->route('events.show', $event);
    }

    // ───────── helpers ─────────

    /** @return array{0: object, 1: string, 2: int} настройки, префикс, базовая сумма в минорных единицах */
    private function paymentContext(Request $request, Payment $payment): array
    {
        abort_if($payment->user_id !== $request->user()->id || $payment->status !== 'pending' || $payment->method !== 'yoomoney', 403);

        if ($payment->organizer_id === null) {
            $settings = PlatformPaymentSetting::first();
            $prefix   = 'sub_';
        } else {
            $settings = PaymentSetting::where('organizer_id', $payment->organizer_id)->first();
            $prefix   = '';
        }
        abort_unless(Pricing::needsChoice($settings, $prefix), 404);

        $base = (int) ($payment->yoomoney_meta['base_minor'] ?? ($payment->amount_minor - $payment->fee_minor - $payment->tax_minor));

        return [$settings, $prefix, $base];
    }

    private function adContext(Request $request, Event $event): PlatformPaymentSetting
    {
        abort_if((int) $event->organizer_id !== (int) $request->user()->id || $event->ad_payment_status !== 'pending', 403);

        $settings = PlatformPaymentSetting::first();
        abort_unless($settings && ($event->ad_payment_method ?: $settings->method) === 'yoomoney' && Pricing::needsChoice($settings, 'ad_'), 404);

        return $settings;
    }

    /** @return array<string,array{label:string,percent:float,fee:int,total:int}> */
    private function options(int $base, ?object $settings, string $prefix): array
    {
        $out = [];
        foreach (Pricing::methodPercents($settings, $prefix) as $method => $percent) {
            $priced = Pricing::apply($base, $settings, $prefix, $method);
            $out[$method] = ['label' => Pricing::METHODS[$method], 'percent' => $percent, 'fee' => $priced['fee'], 'tax' => $priced['tax'], 'total' => $priced['total']];
        }
        return $out;
    }

    /** Налог «сверху», % (0 — налог не добавляется к цене). */
    private function taxPercent(?object $settings, string $prefix): float
    {
        return ($settings->{$prefix . 'tax_mode'} ?? 'included') === 'on_top' ? (float) ($settings->{$prefix . 'tax_percent'} ?? 0) : 0.0;
    }

    private function title(Payment $payment): string
    {
        if ($payment->organizer_id === null) {
            return $this->platform->typeOf($payment) === 'org_pro' ? 'Организатор Pro' : 'Premium';
        }
        return 'Оплата участия' . ($payment->event_id ? ' #' . $payment->event_id : '');
    }
}
