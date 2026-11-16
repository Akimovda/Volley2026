<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CourtBooking;
use App\Models\LocationCourt;
use App\Models\PaymentSetting;
use App\Models\RentalWidget;
use App\Services\CourtBookingService;
use App\Services\PaymentService;
use App\Services\RentalTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/** Бронирование гостем из виджета «Таймлайн»: заявка/предоплата, страница статуса заказа, повторная оплата. */
class RentalBookingPublicController extends Controller
{
    public function __construct(
        private RentalTimelineService $timeline,
        private CourtBookingService $bookings,
        private PaymentService $payments,
    ) {
    }

    private function cors(JsonResponse|Response $r): JsonResponse|Response
    {
        return $r->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept')
            ->header('Access-Control-Max-Age', '600');
    }

    public function preflight(): Response
    {
        return $this->cors(response('', 204));
    }

    private function fail(string $message, int $code = 422): JsonResponse
    {
        return $this->cors(response()->json(['ok' => false, 'error' => $message], $code));
    }

    public function store(Request $request): JsonResponse
    {
        $widget = RentalWidget::where('api_key', (string) $request->input('key', ''))
            ->where('kind', 'timeline')->where('is_active', true)->first();
        if (!$widget) {
            return $this->fail('Invalid key', 403);
        }
        $u = $widget->user;
        if (!$u || !$u->isOrganizerPro() || !$u->is_club_manager) {
            return $this->fail(__('profile.widget_subscription_inactive'), 403);
        }
        $style = \App\Services\WidgetStyleService::resolve((array) ($widget->settings['style'] ?? []), (string) $widget->getSetting('color', '#f59e0b'), 'timeline');
        if (($style['click_mode'] ?? '') !== 'form') {
            return $this->fail('Booking form is disabled', 403);
        }

        $origin = $request->header('Origin', $request->header('Referer', ''));
        if ($origin && !empty($widget->allowed_domains)) {
            $domain = parse_url($origin, PHP_URL_HOST) ?? '';
            if (!$widget->allowsDomain($domain)) {
                return $this->fail('Domain not allowed', 403);
            }
        }

        // Ловушка для ботов: поле скрыто от людей.
        if ((string) $request->input('website', '') !== '') {
            return $this->cors(response()->json(['ok' => true, 'mode' => 'request', 'token' => '', 'status_url' => '']));
        }

        $lang = in_array($request->input('lang'), ['ru', 'en'], true) ? $request->input('lang') : app()->getLocale();
        app()->setLocale($lang);

        $limitKey = 'rental-book:' . $request->ip();
        if (RateLimiter::tooManyAttempts($limitKey, 8)) {
            return $this->fail(__('club.rb_too_many'), 429);
        }
        RateLimiter::hit($limitKey, 600);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'items'            => ['required', 'array', 'min:1', 'max:8'],
            'items.*.court_id' => ['required', 'integer'],
            'items.*.from'     => ['required', 'date_format:H:i'],
            'items.*.to'       => ['required', 'date_format:H:i'],
            'date'             => ['required', 'date_format:Y-m-d'],
            'name'             => ['required', 'string', 'min:3', 'max:150'],
            'phone'            => ['required', 'string', 'max:30', 'regex:/^[+\d\s().-]{10,20}$/'],
            'email'            => ['required', 'email:rfc', 'max:190'],
            'comment'          => ['nullable', 'string', 'max:300'],
            'consent'          => ['accepted'],
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->has('consent') ? __('club.rb_f_need_consent') : __('club.rb_f_err'));
        }
        $data = $validator->validated();

        $phoneDigits = \App\Models\RentalClient::normalizePhone($data['phone']);
        if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
            return $this->fail(__('club.rb_bad_phone'));
        }

        $allowedLocations = $this->timeline->locationsFor($widget, $widget->user_id)->pluck('id')->all();

        $items = [];
        foreach ($data['items'] as $it) {
            $court = LocationCourt::with('direction.location')->find($it['court_id']);
            $loc = $court?->direction?->location;
            if (!$court || !$loc || !in_array($loc->id, $allowedLocations, true)) {
                return $this->fail(__('club.rb_court_unavailable'));
            }
            $tz = $loc->effectiveTimezone();
            $starts = Carbon::parse($data['date'] . ' ' . $it['from'], $tz)->setTimezone('UTC');
            $ends = Carbon::parse($data['date'] . ' ' . $it['to'], $tz)->setTimezone('UTC');
            $items[] = ['court' => $court, 'starts_at' => $starts, 'ends_at' => $ends];
        }

        try {
            $created = $this->bookings->createGuestOrder((int) $widget->user_id, $items, [
                'name' => trim(preg_replace('/\s+/u', ' ', $data['name'])), 'phone' => $data['phone'],
                'email' => mb_strtolower(trim($data['email'])), 'comment' => $data['comment'] ?? null, 'consent' => true, 'locale' => $lang,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }

        foreach ($created as $b) {
            foreach (['classic', 'beach'] as $dir) {
                foreach ([0, 1] as $t) {
                    Cache::forget('rtl_v2_' . $b->court?->direction?->location_id . '_' . $dir . '_' . $data['date'] . '_day_' . $t);
                }
            }
        }

        $first = $created->first();
        $statusUrl = route('rental_booking.status', ['token' => $first->order_token, 'lang' => $lang]);
        $total = $created->sum(fn ($b) => (float) $b->price_total);
        $out = ['ok' => true, 'token' => $first->order_token, 'status_url' => $statusUrl, 'total' => $total,
            'mode' => $first->payment_mode === CourtBooking::PAYMENT_MODE_PREPAID ? 'prepaid' : 'request', 'pay_url' => null];

        if ($out['mode'] === 'prepaid') {
            $settings = PaymentSetting::where('organizer_id', $widget->user_id)->first();
            try {
                $payment = $this->payments->createForOrder($created, $settings, $statusUrl);
                $out['pay_url'] = $payment->yoomoney_confirmation_url;
            } catch (\Throwable $e) {
                report($e);
                Log::error('[RentalBooking] не удалось создать платёж', ['order' => $first->id, 'error' => $e->getMessage()]);
                foreach ($created as $b) {
                    $b->update(['status' => CourtBooking::STATUS_CANCELLED, 'cancelled_by' => 'system', 'cancel_reason' => 'payment_create_failed']);
                }

                return $this->fail(__('club.rb_pay_failed'), 502);
            }
        }

        if ($out['mode'] === 'request') {
            $mailer = app(\App\Services\RentalOrderMailer::class);
            $mailer->toClient('received', $first->order_token);
            $mailer->toOwner('owner_new', $first->order_token);
        }

        return $this->cors(response()->json($out));
    }

    /** Страница статуса заказа (по токену): статусы броней, оплата, подсказка, что дальше. */
    public function status(Request $request, string $token): Response
    {
        if (in_array($request->query('lang'), ['ru', 'en'], true)) {
            app()->setLocale($request->query('lang'));
        }
        $bookings = CourtBooking::where('order_token', $token)->with('court.direction.location')->orderBy('starts_at')->orderBy('court_id')->get();
        abort_if($bookings->isEmpty(), 404);

        return response()->view('widget.rental_booking_status', $this->statusData($token, $bookings))
            ->header('X-Frame-Options', 'ALLOWALL')->header('Content-Security-Policy', 'frame-ancestors *')
            ->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex');
    }

    private function statusData(string $token, $bookings): array
    {
        $first = $bookings->first();
        $active = $bookings->whereIn('status', CourtBooking::ACTIVE_STATUSES);
        $pendingPay = $first->payment_mode === CourtBooking::PAYMENT_MODE_PREPAID
            && $bookings->where('status', CourtBooking::STATUS_PENDING)->isNotEmpty()
            && $first->expires_at && now()->lt($first->expires_at);

        return [
            'token' => $token, 'bookings' => $bookings, 'location' => $first->court?->direction?->location,
            'total' => $active->sum(fn ($b) => (float) $b->price_total), 'canPay' => $pendingPay,
        ];
    }

    public function pay(Request $request, string $token)
    {
        if (in_array($request->query('lang'), ['ru', 'en'], true)) {
            app()->setLocale($request->query('lang'));
        }
        $bookings = CourtBooking::where('order_token', $token)->with('court.direction.location')->orderBy('id')->get();
        abort_if($bookings->isEmpty(), 404);
        $first = $bookings->first();
        $pending = $bookings->where('status', CourtBooking::STATUS_PENDING);

        if ($first->payment_mode !== CourtBooking::PAYMENT_MODE_PREPAID || $pending->isEmpty()
            || !$first->expires_at || now()->gte($first->expires_at)) {
            return back()->with('error', __('club.booking_pay_expired'));
        }

        $settings = PaymentSetting::where('organizer_id', $first->court?->direction?->location?->owner_id)->first();
        if (!$settings || !$settings->isYoomoneyReady()) {
            return back()->with('error', __('club.booking_pay_not_available'));
        }

        try {
            $payment = $this->payments->createForOrder($pending->values(), $settings, route('rental_booking.status', ['token' => $token, 'lang' => app()->getLocale()]));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('club.booking_pay_failed'));
        }

        return redirect()->away($payment->yoomoney_confirmation_url);
    }
}
