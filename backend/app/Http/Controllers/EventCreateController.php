<?php
// app/Http/Controllers/EventCreateController.php

namespace App\Http\Controllers;

use RRule\RRule;
use App\Models\Event;
use App\Models\EventGameSetting;
use App\Models\EventOccurrence;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
// ✅ Spatie Media model
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EventCreateController extends Controller
{
    /**
     * ✅ Было: "Шаг 0" со ссылками.
     * ✅ Стало: /events/create = мастер создания (бывший from-scratch).
     */
    public function choose(Request $request)
    {
        return $this->create($request);
    }

    /**
     * /events/create/from-template (хотим удалить из роутов, но пусть безопасно редиректит)
     */
    public function fromTemplate(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        $this->ensureCanCreateEvents($user);

        return redirect()->route('events.create.event_management', ['tab' => 'archive']);
    }

    /**
     * POST /events/{event}/copy (legacy)
     * ✅ Было: редирект на /events/create/from-scratch?from_event_id=ID
     * ✅ Стало: редирект на /events/create?from_event_id=ID
     */
    public function fromEvent(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        $this->ensureCanCreateEvents($user);

        $role = (string)($user->role ?? 'user');

        // права: admin может всё, organizer только свои, staff только своего organizer
        if ($role !== 'admin') {
            if ($role === 'organizer') {
                if ((int)$event->organizer_id !== (int)$user->id) abort(403);
            } elseif ($role === 'staff') {
                $orgId = $this->resolveOrganizerIdForCreator($user);
                if ((int)$orgId <= 0) abort(403);
                if ((int)$event->organizer_id !== (int)$orgId) abort(403);
            } else {
                abort(403);
            }
        }

        return redirect()->to('/events/create?from_event_id=' . (int)$event->id);
    }

    /**
     * ⚠️ У тебя сейчас есть отдельный EventManagementController@index,
     * но этот метод оставляем, чтобы ничего внезапно не отвалилось,
     * если где-то он ещё используется.
     */
    public function eventManagement(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        $this->ensureCanCreateEvents($user);

        $tab = (string)$request->query('tab', 'templates');
        $tab = in_array($tab, ['templates', 'archive', 'mine'], true) ? $tab : 'templates';

        $q = Event::query()
            ->with(['location:id,name,city,address'])
            ->select('events.*')
            ->orderByDesc('events.id');

        if ($tab === 'templates') {
            if (Schema::hasColumn('events', 'is_template')) {
                $q->where('events.is_template', true);
            } else {
                $q->whereRaw('1=0');
            }
        }

        if ($tab === 'archive') {
            $now = now();
            if (Schema::hasColumn('events', 'ends_at') && Schema::hasColumn('events', 'starts_at')) {
                $q->where(function ($w) use ($now) {
                    $w->whereNotNull('events.ends_at')->where('events.ends_at', '<', $now)
                        ->orWhere(function ($w2) use ($now) {
                            $w2->whereNull('events.ends_at')
                                ->whereNotNull('events.starts_at')
                                ->where('events.starts_at', '<', $now);
                        });
                });
            } elseif (Schema::hasColumn('events', 'starts_at')) {
                $q->whereNotNull('events.starts_at')->where('events.starts_at', '<', $now);
            } else {
                $q->whereRaw('1=0');
            }
        }

        if ($tab === 'mine') {
            $uid = (int)$user->id;
            $ownerCol = null;
            foreach (['organizer_id', 'owner_id', 'created_by', 'user_id'] as $c) {
                if (Schema::hasColumn('events', $c)) { $ownerCol = $c; break; }
            }
            if ($ownerCol) {
                $q->where("events.$ownerCol", $uid);
            } else {
                $q->whereRaw('1=0');
            }
        }

        $q->leftJoin('event_game_settings as egs', 'egs.event_id', '=', 'events.id')
            ->leftJoin('event_registrations as er', 'er.event_id', '=', 'events.id')
            ->addSelect([
                DB::raw('COALESCE(egs.max_players, 0) as max_players'),
                DB::raw('COUNT(er.id) as registered_total'),
            ])
            ->groupBy('events.id', 'egs.max_players');

        $events = $q->paginate(20)->withQueryString();

        return view('events.event_management', [
            'tab' => $tab,
            'events' => $events,
        ]);
    }

    /**
     * /events/create (мастер создания)
     */
    public function create(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        $this->ensureCanCreateEvents($user);

        $role = (string) ($user->role ?? 'user');
        $organizerId = $this->resolveOrganizerIdForCreator($user);

        $locationsQuery = Location::query()->orderBy('name');
        if ($role !== 'admin') {
            $locationsQuery->whereNull('organizer_id');
        }
        $locations = $locationsQuery->get();

        $organizers = collect();
        if ($role === 'admin') {
            $organizers = User::query()
                ->where('role', 'organizer')
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        // ✅ Prefill from existing event
        $prefill = [];
        $fromId = (int) $request->query('from_event_id', 0);
        if ($fromId > 0 && empty(old())) {
            $src = Event::with('gameSettings')->find($fromId);
            if ($src) {
                $prefill = $this->getEventPrefillData($src);
                $prefill['_prefill_source_event_id'] = $fromId;
            }
        }

        // ✅ trainer prefill label
        $trainerLabel = null;
        if (!empty($prefill['trainer_user_id'] ?? null)) {
            $tu = User::query()
                ->whereKey((int)$prefill['trainer_user_id'])
                ->first(['id','name','email']);
            if ($tu) $trainerLabel = ($tu->name ?: $tu->email) . ' (#' . (int)$tu->id . ')';
        }

        // ✅ Галерея пользователя (Media Library) — отдадим в форму
        $userCovers = Media::query()
            ->where('model_type', 'App\\Models\\User')
            ->where('model_id', (int)$user->id)
            ->orderByDesc('id')
            ->limit(60)
            ->get(['id', 'file_name', 'disk', 'collection_name', 'created_at']);

        return view('events.create', [
            'locations' => $locations,
            'organizers' => $organizers,
            'canChooseOrganizer' => $role === 'admin',
            'resolvedOrganizerId' => $organizerId,
            'resolvedOrganizerLabel' => $role === 'admin'
                ? null
                : (($role === 'organizer') ? 'Вы создаёте как organizer' : 'Вы создаёте как staff (привязан к organizer)'),
            'prefill' => $prefill,
            'userCovers' => $userCovers,
            'trainerPrefillLabel' => $trainerLabel,
        ]);
    }

    /**
     * ✅ AJAX поиск пользователей для выбора тренера
     * Ожидаемый роут (пример):
     * Route::get('/ajax/users/search', [EventCreateController::class, 'searchUsers'])->name('ajax.users.search');
     */
    public function searchUsers(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['ok' => false, 'items' => []], 401);
        $this->ensureCanCreateEvents($user);

        $q = trim((string)$request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['ok' => true, 'items' => []]);
        }

        $like = '%' . str_replace(['%', '_'], ['\%','\_'], $q) . '%';

        $items = User::query()
            ->where(function ($w) use ($like) {
                // максимально совместимо: name/email + (если есть) nickname/username
                $w->where('name', 'like', $like)
                  ->orWhere('email', 'like', $like);

                if (Schema::hasColumn('users', 'nickname')) {
                    $w->orWhere('nickname', 'like', $like);
                }
                if (Schema::hasColumn('users', 'username')) {
                    $w->orWhere('username', 'like', $like);
                }
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['id','name','email'])
            ->map(function ($u) {
                return [
                    'id' => (int)$u->id,
                    'label' => trim(($u->name ?: $u->email) . ' (#' . (int)$u->id . ')'),
                ];
            })
            ->values()
            ->all();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        $this->ensureCanCreateEvents($user);

        $role = (string) ($user->role ?? 'user');
        $resolvedOrganizerId = $this->resolveOrganizerIdForCreator($user);

        $organizerId = $resolvedOrganizerId;
        if ($role === 'admin') {
            $organizerId = (int) $request->input('organizer_id');
        }

        $saveAsTemplate = (bool) $request->boolean('save_as_template', false);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'direction' => ['required', 'in:classic,beach'],
            'format' => ['required', 'in:game,training,training_game,coach_student,tournament,camp'],
            'allow_registration' => ['required', 'boolean'],

            // ✅ trainer
            'trainer_user_id' => ['nullable', 'integer'],

            'timezone' => ['required', 'string', 'max:64'],
            'starts_at_local' => [$saveAsTemplate ? 'nullable' : 'required', 'date_format:Y-m-d\TH:i'],
            'ends_at_local' => ['nullable', 'date_format:Y-m-d\TH:i'],

            'classic_level_min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'classic_level_max' => ['nullable', 'integer', 'min:0', 'max:10'],
            'beach_level_min'   => ['nullable', 'integer', 'min:0', 'max:10'],
            'beach_level_max'   => ['nullable', 'integer', 'min:0', 'max:10'],

            'game_subtype' => ['nullable', 'in:4x4,4x2,5x1'],
            'game_min_players' => ['nullable', 'integer', 'min:1', 'max:99'],
            'game_max_players' => ['nullable', 'integer', 'min:1', 'max:99'],
            'game_allow_girls' => ['nullable', 'boolean'],
            'game_girls_max' => ['nullable', 'integer', 'min:0', 'max:99'],
            'game_libero_mode' => ['nullable', 'in:with_libero,without_libero'],
            'game_has_libero' => ['nullable', 'boolean'],
            'game_positions' => ['nullable', 'array'],
            'game_positions.*' => ['in:setter,outside,opposite,middle,libero'],

            'game_gender_policy' => ['nullable', 'in:only_male,only_female,mixed_open,mixed_limited'],
            'game_gender_limited_side' => ['nullable', 'in:male,female'],
            'game_gender_limited_max' => ['nullable', 'integer', 'min:0', 'max:99'],
            'game_gender_limited_positions' => ['nullable', 'array'],
            'game_gender_limited_positions.*' => ['in:setter,outside,opposite,middle,libero'],

            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'is_private' => ['nullable', 'boolean'],

            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_rule' => ['nullable', 'string'],

            'recurrence_type' => ['nullable', 'in:daily,weekly,monthly'],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_months' => ['nullable', 'array'],
            'recurrence_months.*' => ['integer', 'min:1', 'max:12'],

            'is_paid' => ['nullable', 'boolean'],
            'price_text' => ['nullable', 'string', 'max:255'],
            'requires_personal_data' => ['nullable', 'boolean'],

            'organizer_id' => ['nullable', 'integer'],
            'save_as_template' => ['nullable', 'boolean'],
            'template_name' => ['nullable', 'string', 'max:255'],
            'template_payload_text' => ['nullable', 'string'],

            // ✅ cover
            'cover_upload' => ['nullable', 'file', 'image', 'max:5120'],
            'cover_media_id' => ['nullable', 'integer'],
        ]);

        // ✅ trainer: обязателен для training / training_game (но мягко: только если колонка есть)
        $format = (string)($data['format'] ?? '');
        $trainerId = isset($data['trainer_user_id']) ? (int)$data['trainer_user_id'] : 0;

        $needTrainer = in_array($format, ['training', 'training_game'], true);
        if ($needTrainer) {
            if ($trainerId <= 0) {
                return back()->withInput()->withErrors([
                    'trainer_user_id' => 'Выберите тренера для формата "Тренировка" / "Тренировка + Игра".',
                ]);
            }
            $exists = User::query()->whereKey($trainerId)->exists();
            if (!$exists) {
                return back()->withInput()->withErrors([
                    'trainer_user_id' => 'Выбранный тренер не найден.',
                ]);
            }
        } else {
            $trainerId = 0;
        }

        // ✅ чистим уровни по направлению
        if (($data['direction'] ?? null) === 'classic') {
            $data['beach_level_min'] = null;
            $data['beach_level_max'] = null;
        } elseif (($data['direction'] ?? null) === 'beach') {
            $data['classic_level_min'] = null;
            $data['classic_level_max'] = null;
        }

        if ($role === 'staff' && empty($resolvedOrganizerId)) {
            return back()->withInput()->with('error', 'Staff не привязан к organizer — создание мероприятий запрещено.');
        }

        if ($role === 'admin') {
            if (empty($organizerId)) {
                return back()->withInput()->with('error', 'Выберите organizer для мероприятия.');
            }
            $org = User::query()->where('id', $organizerId)->where('role', 'organizer')->exists();
            if (!$org) {
                return back()->withInput()->with('error', 'Неверный organizer_id.');
            }
        }

        if (($data['format'] ?? null) === 'coach_student' && ($data['direction'] ?? null) !== 'beach') {
            return back()->withInput()->with('error', 'Формат "Тренер+ученик" доступен только для пляжного волейбола.');
        }

        $allowReg = (bool) ($data['allow_registration'] ?? false);
        if (!$allowReg) {
            $data['is_recurring'] = 0;
            $data['recurrence_rule'] = '';
        }

        $isRecurring = (bool) ($data['is_recurring'] ?? false);
        $recRule = trim((string) ($data['recurrence_rule'] ?? ''));

        if ($isRecurring) {
            if ($recRule === '') {
                $type = (string)($data['recurrence_type'] ?? '');
                $interval = (int)($data['recurrence_interval'] ?? 1);
                $months = $data['recurrence_months'] ?? [];

                if (is_string($months)) $months = [$months];
                if (!is_array($months)) $months = [];
                $months = array_values(array_unique(array_map('intval', $months)));
                $months = array_values(array_filter($months, fn($m) => $m >= 1 && $m <= 12));

                $freqMap = [
                    'daily' => 'DAILY',
                    'weekly' => 'WEEKLY',
                    'monthly' => 'MONTHLY',
                ];

                if (!isset($freqMap[$type])) {
                    return back()->withInput()->with('error', 'Выбери тип повторения (ежедневно/еженедельно/ежемесячно).');
                }

                $rruleArr = [
                    'FREQ' => $freqMap[$type],
                    'INTERVAL' => max(1, $interval),
                ];

                if ($type === 'monthly' && !empty($months)) {
                    $rruleArr['BYMONTH'] = $months;
                }

                $rruleString = (new RRule($rruleArr))->rfcString();
                $recRule = preg_replace('/^RRULE:/', '', $rruleString);
                $recRule = trim((string)$recRule);
            }

            if ($recRule === '') {
                return back()->withInput()->with('error', 'Для повторяющегося мероприятия нужно указать recurrence_rule.');
            }
        } else {
            $recRule = '';
        }

        $isPaid = (bool) ($data['is_paid'] ?? false);
        $priceText = trim((string) ($data['price_text'] ?? ''));
        if ($isPaid && $priceText === '') {
            return back()->withInput()->with('error', 'Укажите стоимость/условия оплаты (price_text).');
        }
        if (!$isPaid) $priceText = '';

        $locationId = (int) $data['location_id'];
        $location = Location::query()->whereKey($locationId)->first();
        if (!$location) {
            return back()->withInput()->with('error', 'Локация не найдена.');
        }
        if ($role !== 'admin' && !is_null($location->organizer_id)) {
            return back()->withInput()->with('error', 'Организатор может выбирать только локации, созданные админом.');
        }

        // Dates
        $tz = (string) $data['timezone'];
        $startsUtc = null;
        $endsUtc = null;

        if (!$saveAsTemplate) {
            try {
                $startsUtc = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at_local'], $tz)->utc();
                if (!empty($data['ends_at_local'])) {
                    $endsUtc = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at_local'], $tz)->utc();
                    if ($endsUtc->lessThanOrEqualTo($startsUtc)) {
                        return back()->withInput()->withErrors([
                            'ends_at_local' => 'ends_at должен быть позже starts_at.',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                return back()->withInput()->with('error', 'Неверные дата/время или timezone.');
            }

            $nowUtc = CarbonImmutable::now('UTC');
            if ($startsUtc->lessThan($nowUtc)) {
                return back()->withInput()->withErrors([
                    'starts_at_local' => 'Нельзя создать мероприятие в прошлом. Укажи дату/время в будущем.',
                ]);
            }
        }

        $classicMin = $data['classic_level_min'] ?? null;
        $classicMax = $data['classic_level_max'] ?? null;
        if (!is_null($classicMin) && !is_null($classicMax) && (int)$classicMax < (int)$classicMin) {
            return back()->withInput()->withErrors([
                'classic_level_max' => 'Classic: "До (max)" не может быть меньше "От (min)".',
            ]);
        }

        $beachMin = $data['beach_level_min'] ?? null;
        $beachMax = $data['beach_level_max'] ?? null;
        if (!is_null($beachMin) && !is_null($beachMax) && (int)$beachMax < (int)$beachMin) {
            return back()->withInput()->withErrors([
                'beach_level_max' => 'Beach: "До (max)" не может быть меньше "От (min)".',
            ]);
        }

        $isGameClassic = ($data['direction'] ?? null) === 'classic' && ($data['format'] ?? null) === 'game';
        if ($isGameClassic) {
            if (empty($data['game_subtype'])) {
                return back()->withInput()->with('error', 'Выбери подтип игры (4×4 / 4×2 / 5×1).');
            }
            if (empty($data['game_max_players'])) {
                return back()->withInput()->with('error', 'Укажи максимум участников для игры.');
            }

            $minPlayers = isset($data['game_min_players']) ? (int)$data['game_min_players'] : null;
            $maxPlayers = isset($data['game_max_players']) ? (int)$data['game_max_players'] : null;
            if (!is_null($minPlayers) && !is_null($maxPlayers) && $maxPlayers < $minPlayers) {
                return back()->withInput()->withErrors([
                    'game_max_players' => 'Макс. участников не может быть меньше Мин. участников.',
                ]);
            }

            $hasNewGender = isset($data['game_gender_policy']) && trim((string)$data['game_gender_policy']) !== '';
            if (!$hasNewGender) {
                $allowGirlsLegacy = (bool) ($data['game_allow_girls'] ?? false);
                if ($allowGirlsLegacy && ((string) ($data['game_girls_max'] ?? '') === '')) {
                    return back()->withInput()->with('error', 'Укажи максимум девушек (если допуск девушек включён).');
                }
            }
        } else {
            $data['game_subtype'] = null;
            $data['game_min_players'] = null;
            $data['game_max_players'] = null;
            $data['game_allow_girls'] = 0;
            $data['game_girls_max'] = null;
            $data['game_has_libero'] = 0;
            $data['game_libero_mode'] = null;
            $data['game_positions'] = [];
            $data['game_gender_policy'] = null;
            $data['game_gender_limited_side'] = null;
            $data['game_gender_limited_max'] = null;
            $data['game_gender_limited_positions'] = null;
        }

        $genderPolicy = (string) ($data['game_gender_policy'] ?? '');
        $genderLimitedSide = $data['game_gender_limited_side'] ?? null;
        $genderLimitedMax = isset($data['game_gender_limited_max']) ? (int) $data['game_gender_limited_max'] : null;

        $genderLimitedPositions = $data['game_gender_limited_positions'] ?? null;
        if (is_string($genderLimitedPositions)) $genderLimitedPositions = [$genderLimitedPositions];
        if (is_array($genderLimitedPositions)) {
            $genderLimitedPositions = array_values(array_unique(array_map('strval', $genderLimitedPositions)));
            if (count($genderLimitedPositions) === 0) $genderLimitedPositions = null;
        } else {
            $genderLimitedPositions = null;
        }

        $legacyAllowGirls = (bool) ($data['game_allow_girls'] ?? true);
        $legacyGirlsMax = (isset($data['game_girls_max']) && (string) $data['game_girls_max'] !== '')
            ? (int) $data['game_girls_max']
            : null;

        if ($isGameClassic) {
            if ($genderPolicy === '') {
                if (!$legacyAllowGirls) {
                    $genderPolicy = 'only_male';
                } elseif (!is_null($legacyGirlsMax)) {
                    $genderPolicy = 'mixed_limited';
                    $genderLimitedSide = 'female';
                    $genderLimitedMax = $legacyGirlsMax;
                    $genderLimitedPositions = null;
                } else {
                    $genderPolicy = 'mixed_open';
                }
            }

            if ($genderPolicy === 'mixed_limited') {
                if (!$genderLimitedSide) {
                    return back()->withInput()->withErrors([
                        'game_gender_limited_side' => 'Укажи, кого ограничиваем (М или Ж).',
                    ]);
                }
                if (is_null($genderLimitedMax)) {
                    return back()->withInput()->withErrors([
                        'game_gender_limited_max' => 'Укажи максимум мест для ограничиваемых.',
                    ]);
                }
            } else {
                $genderLimitedSide = null;
                $genderLimitedMax = null;
                $genderLimitedPositions = null;
            }
        } else {
            $genderPolicy = '';
            $genderLimitedSide = null;
            $genderLimitedMax = null;
            $genderLimitedPositions = null;
        }

        $templateName = trim((string) ($data['template_name'] ?? ''));
        if ($saveAsTemplate && $templateName === '') {
            return back()->withInput()->with('error', 'Для сохранения шаблона укажи template_name.');
        }

        $isPrivate = (bool) ($data['is_private'] ?? false);

        DB::beginTransaction();
        try {
            $event = new Event();
            $event->title = $saveAsTemplate ? $templateName : $data['title'];
            $event->requires_personal_data = (bool) ($data['requires_personal_data'] ?? false);

            $event->classic_level_min = $classicMin;
            if (Schema::hasColumn('events', 'classic_level_max')) {
                $event->classic_level_max = $classicMax;
            }

            $event->beach_level_min = $beachMin;
            if (Schema::hasColumn('events', 'beach_level_max')) {
                $event->beach_level_max = $beachMax;
            }

            $event->organizer_id = $organizerId;
            $event->location_id = $locationId;

            $event->timezone = $tz;
            $event->starts_at = $startsUtc;
            $event->ends_at = $endsUtc;

            $event->direction = $data['direction'];
            $event->format = $data['format'];

            // ✅ trainer save (мягко)
            if (Schema::hasColumn('events', 'trainer_user_id')) {
                $event->trainer_user_id = ($trainerId > 0) ? $trainerId : null;
            } elseif (Schema::hasColumn('events', 'trainer_id')) {
                $event->trainer_id = ($trainerId > 0) ? $trainerId : null;
            }

            $event->is_private = $isPrivate;
            $event->allow_registration = $allowReg;

            $event->is_paid = $isPaid;
            $event->price_text = $priceText;

            $event->visibility = $isPrivate ? 'private' : 'public';
            if ($isPrivate && empty($event->public_token)) {
                $event->public_token = (string) Str::uuid();
            }

            if (Schema::hasColumn('events', 'is_template')) {
                $event->is_template = $saveAsTemplate ? 1 : 0;
            }

            // recurrence_rule кладём в event (как у тебя было)
            if (Schema::hasColumn('events', 'is_recurring')) {
                $event->is_recurring = (bool)$isRecurring;
            }
            if (Schema::hasColumn('events', 'recurrence_rule')) {
                $event->recurrence_rule = $recRule;
            }

            $event->save();

            // ✅ создаём первое occurrence для НЕ-шаблона
            if (!$saveAsTemplate) {
                EventOccurrence::create([
                    'event_id'  => $event->id,
                    'starts_at' => $event->starts_at,
                    'ends_at'   => $event->ends_at,
                    'timezone'  => $event->timezone ?: 'UTC',
                    'uniq_key'  => "event:{$event->id}:" . ($event->starts_at ? $event->starts_at->format('YmdHis') : 'no_start'),
                ]);
            }

            // ✅ cover
            if ($request->hasFile('cover_upload')) {
                $event->addMediaFromRequest('cover_upload')->toMediaCollection('cover');
            } else {
                $coverMediaId = (int)($data['cover_media_id'] ?? 0);
                if ($coverMediaId > 0) {
                    $m = Media::query()
                        ->where('id', $coverMediaId)
                        ->where('model_type', 'App\\Models\\User')
                        ->where('model_id', (int)$user->id)
                        ->first();
                    if ($m) {
                        $m->copy($event, 'cover');
                    }
                }
            }

            if ($isGameClassic) {
                $subtype = (string) $data['game_subtype'];

                $liberoMode = null;
                if ($subtype === '5x1') {
                    $liberoMode = $data['game_libero_mode'] ?? null;
                    if (!$liberoMode) {
                        $hasLiberoLegacy = (bool) ($data['game_has_libero'] ?? false);
                        $liberoMode = $hasLiberoLegacy ? 'with_libero' : 'without_libero';
                    }
                    $liberoMode = $liberoMode ?: 'with_libero';
                }

                $positions = $this->autoPositionsForClassic($subtype, $liberoMode);

                $egsPayload = [
                    'subtype' => $subtype,
                    'libero_mode' => $liberoMode,
                    'min_players' => $data['game_min_players'] ?? null,
                    'max_players' => $data['game_max_players'] ?? null,
                    'allow_girls' => (bool) ($data['game_allow_girls'] ?? false),
                    'girls_max' => $data['game_girls_max'] ?? null,
                    'positions' => $positions,
                ];

                if (Schema::hasColumn('event_game_settings', 'gender_policy')) {
                    $egsPayload['gender_policy'] = ($genderPolicy !== '') ? $genderPolicy : null;
                }
                if (Schema::hasColumn('event_game_settings', 'gender_limited_side')) {
                    $egsPayload['gender_limited_side'] = $genderLimitedSide;
                }
                if (Schema::hasColumn('event_game_settings', 'gender_limited_max')) {
                    $egsPayload['gender_limited_max'] = $genderLimitedMax;
                }
                if (Schema::hasColumn('event_game_settings', 'gender_limited_positions')) {
                    $egsPayload['gender_limited_positions'] = $genderLimitedPositions;
                }

                EventGameSetting::updateOrCreate(
                    ['event_id' => $event->id],
                    $egsPayload
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Ошибка сохранения мероприятия: ' . $e->getMessage());
        }

        return redirect()->to('/events')->with('status', $saveAsTemplate ? 'Шаблон создан ✅' : 'Мероприятие создано ✅');
    }

    private function getEventPrefillData(Event $src): array
    {
        $prefill = Arr::only($src->toArray(), [
            'title',
            'direction',
            'format',
            'location_id',
            'timezone',
            'requires_personal_data',
            'classic_level_min',
            'classic_level_max',
            'beach_level_min',
            'beach_level_max',
            'is_paid',
            'price_text',
            'is_private',
            'allow_registration',
            'is_recurring',
            'recurrence_rule',
            // ✅ trainer
            'trainer_user_id',
            'trainer_id',
        ]);

        // ✅ нормализуем trainer в один ключ
        if (empty($prefill['trainer_user_id']) && !empty($prefill['trainer_id'])) {
            $prefill['trainer_user_id'] = $prefill['trainer_id'];
        }

        $gs = $src->gameSettings;
        if ($gs) {
            $prefill['game_subtype'] = $gs->subtype;
            $prefill['game_libero_mode'] = $gs->libero_mode;
            $prefill['game_min_players'] = $gs->min_players;
            $prefill['game_max_players'] = $gs->max_players;
            $prefill['game_gender_policy'] = $gs->gender_policy;
            $prefill['game_gender_limited_side'] = $gs->gender_limited_side;
            $prefill['game_gender_limited_max'] = $gs->gender_limited_max;
            $prefill['game_gender_limited_positions'] = is_array($gs->gender_limited_positions) ? $gs->gender_limited_positions : null;
            $prefill['game_allow_girls'] = (bool)($gs->allow_girls ?? true);
            $prefill['game_girls_max'] = $gs->girls_max;
        }

        unset($prefill['starts_at'], $prefill['ends_at'], $prefill['public_token']);
        return $prefill;
    }

    private function autoPositionsForClassic(string $subtype, ?string $liberoMode): array
    {
        $subtype = trim($subtype);
        $liberoMode = $liberoMode ? trim($liberoMode) : null;

        if ($subtype === '4x4') return ['setter', 'outside', 'opposite'];
        if ($subtype === '4x2') return ['setter', 'outside'];
        if ($subtype === '5x1') {
            if ($liberoMode === 'with_libero') {
                return ['setter', 'outside', 'opposite', 'middle', 'libero'];
            }
            return ['setter', 'outside', 'opposite', 'middle'];
        }
        return [];
    }

    private function ensureCanCreateEvents($user): void
    {
        if (!$user) abort(403);
        $role = (string) ($user->role ?? 'user');
        if (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
            abort(403);
        }
    }

    private function resolveOrganizerIdForCreator($user): int
    {
        $role = (string) ($user->role ?? 'user');

        if ($role === 'organizer') return (int) $user->id;

        if ($role === 'staff') {
            $row = DB::table('organizer_staff')
                ->where('staff_user_id', (int) $user->id)
                ->orderBy('id')
                ->first(['organizer_id']);

            return $row ? (int) $row->organizer_id : 0;
        }

        return 0; // admin
    }
}
