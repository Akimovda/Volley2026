<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventCreateController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        $this->ensureCanCreateEvents($user);

        $role = (string)($user->role ?? 'user');
        $organizerId = $this->resolveOrganizerIdForCreator($user);

        // Локации:
        // - admin: все
        // - organizer/staff: свои + общие (organizer_id null)
        $locationsQuery = Location::query()->orderBy('name');
        if ($role !== 'admin') {
            $locationsQuery->where(function ($q) use ($organizerId) {
                $q->whereNull('organizer_id')
                  ->orWhere('organizer_id', $organizerId);
            });
        }
        $locations = $locationsQuery->get();

        // Организаторы для админа (выбор organizer_id)
        $organizers = collect();
        if ($role === 'admin') {
            $organizers = User::query()
                ->where('role', 'organizer')
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        return view('events.create', [
            'locations' => $locations,
            'organizers' => $organizers,
            'canChooseOrganizer' => $role === 'admin',
            'resolvedOrganizerId' => $organizerId,
            'resolvedOrganizerLabel' => $role === 'admin'
                ? null
                : (($role === 'organizer') ? 'Вы создаёте как organizer' : 'Вы создаёте как staff (привязан к organizer)'),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->ensureCanCreateEvents($user);

        $role = (string)($user->role ?? 'user');
        $resolvedOrganizerId = $this->resolveOrganizerIdForCreator($user);

        // admin может выбрать organizer_id, organizer/staff — игнорируем input
        $organizerId = $resolvedOrganizerId;
        if ($role === 'admin') {
            $organizerId = (int) $request->input('organizer_id');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],

            'direction' => ['required', 'in:classic,beach'],
            'format' => ['required', 'in:free_play,game,training,training_game,coach_student,tournament,camp'],

            'timezone' => ['required', 'string', 'max:64'],
            'starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at_local' => ['nullable', 'date_format:Y-m-d\TH:i'],

            'location_id' => ['required', 'integer'],

            'is_private' => ['nullable', 'boolean'],
            'allow_registration' => ['nullable', 'boolean'],

            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_rule' => ['nullable', 'string'],

            'is_paid' => ['nullable', 'boolean'],
            'price_text' => ['nullable', 'string', 'max:255'],

            // старые поля (оставим твою логику)
            'requires_personal_data' => ['nullable', 'boolean'],
            'classic_level_min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'beach_level_min' => ['nullable', 'integer', 'min:0', 'max:10'],

            // admin-only
            'organizer_id' => ['nullable', 'integer'],
        ]);

        // staff должен иметь organizer
        if ($role === 'staff' && empty($resolvedOrganizerId)) {
            return back()->withInput()->with('error', 'Staff не привязан к organizer — создание мероприятий запрещено.');
        }

        // admin: organizer_id обязателен и должен быть organizer
        if ($role === 'admin') {
            if (empty($organizerId)) {
                return back()->withInput()->with('error', 'Выберите organizer для мероприятия.');
            }
            $org = User::query()->where('id', $organizerId)->where('role', 'organizer')->exists();
            if (!$org) {
                return back()->withInput()->with('error', 'Неверный organizer_id.');
            }
        }

        // coach_student только beach
        if (($data['format'] ?? null) === 'coach_student' && ($data['direction'] ?? null) !== 'beach') {
            return back()->withInput()->with('error', 'Формат "Тренер+ученик" доступен только для пляжного волейбола.');
        }

        // recurring: если включено — правило обязательно
        $isRecurring = (bool)($data['is_recurring'] ?? false);
        $recRule = trim((string)($data['recurrence_rule'] ?? ''));
        if ($isRecurring && $recRule === '') {
            return back()->withInput()->with('error', 'Для повторяющегося мероприятия нужно указать recurrence_rule.');
        }
        if (!$isRecurring) {
            $recRule = '';
        }

        // paid: если включено — цена обязательна
        $isPaid = (bool)($data['is_paid'] ?? false);
        $priceText = trim((string)($data['price_text'] ?? ''));
        if ($isPaid && $priceText === '') {
            return back()->withInput()->with('error', 'Укажите стоимость/условия оплаты (price_text).');
        }
        if (!$isPaid) {
            $priceText = '';
        }

        // Проверяем доступность location_id
        $locationId = (int)$data['location_id'];
        $location = Location::query()->where('id', $locationId)->first();
        if (!$location) {
            return back()->withInput()->with('error', 'Локация не найдена.');
        }

        if ($role !== 'admin') {
            // organizer/staff: только свои + общие
            if (!is_null($location->organizer_id) && (int)$location->organizer_id !== (int)$organizerId) {
                return back()->withInput()->with('error', 'Нельзя выбрать чужую локацию.');
            }
        }

        // Конвертация local datetime + timezone -> UTC (храним момент времени)
        $tz = (string)$data['timezone'];
        try {
            $startsUtc = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at_local'], $tz)->utc();
            $endsUtc = null;

            if (!empty($data['ends_at_local'])) {
                $endsUtc = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at_local'], $tz)->utc();
                if ($endsUtc->lessThanOrEqualTo($startsUtc)) {
                    return back()->withInput()->with('error', 'ends_at должен быть позже starts_at.');
                }
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Неверные дата/время или timezone.');
        }

        $isPrivate = (bool)($data['is_private'] ?? false);
        $allowReg = (bool)($data['allow_registration'] ?? false);

        $requiresPersonal = (bool)($data['requires_personal_data'] ?? false);
        $classicMin = $data['classic_level_min'] ?? null;
        $beachMin = $data['beach_level_min'] ?? null;

        DB::beginTransaction();
        try {
            $event = new Event();

            // 1) твои старые поля (не ломаем)
            $event->title = $data['title'];
            $event->requires_personal_data = $requiresPersonal;
            $event->classic_level_min = $classicMin;
            $event->beach_level_min = $beachMin;

            // 2) новые поля
            $event->organizer_id = $organizerId;
            $event->location_id = $locationId;
            $event->timezone = $tz;
            $event->starts_at = $startsUtc;
            $event->ends_at = $endsUtc;

            $event->direction = $data['direction'];
            $event->format = $data['format'];
            $event->is_private = $isPrivate;
            $event->allow_registration = $allowReg;

            $event->is_recurring = $isRecurring;
            $event->recurrence_rule = $recRule;

            $event->is_paid = $isPaid;
            $event->price_text = $priceText;

            // 3) compatibility: заполняем старые аналоги (если колонки есть)
            $event->sport_category = $data['direction'];
            $event->event_format = $data['format'];
            $event->is_registrable = $allowReg;
            $event->rrule = $recRule !== '' ? Str::limit($recRule, 250, '') : null;

            // visibility/public_token — “приватное по ссылке”
            // visibility: public|private
            $event->visibility = $isPrivate ? 'private' : 'public';
            if ($isPrivate && empty($event->public_token)) {
                $event->public_token = (string) Str::uuid();
            }
            if (!$isPrivate) {
                // токен можно оставить, но логичнее не генерировать
                // (не трогаем если уже был)
            }

            $event->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Ошибка сохранения мероприятия: '.$e->getMessage());
        }

        return redirect()->to('/events')->with('status', 'Мероприятие создано ✅');
    }

    private function ensureCanCreateEvents($user): void
    {
        $role = (string)($user->role ?? 'user');
        if (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
            abort(403);
        }
    }

    /**
     * Возвращает organizer_id, под которым нужно создавать события:
     * - admin: 0 (выбирает в форме)
     * - organizer: user.id
     * - staff: organizer_id из organizer_staff (первая запись)
     */
    private function resolveOrganizerIdForCreator($user): int
    {
        $role = (string)($user->role ?? 'user');

        if ($role === 'organizer') {
            return (int)$user->id;
        }

        if ($role === 'staff') {
            $row = DB::table('organizer_staff')
                ->where('staff_user_id', (int)$user->id)
                ->orderBy('id')
                ->first(['organizer_id']);

            return $row ? (int)$row->organizer_id : 0;
        }

        // admin
        return 0;
    }
}
