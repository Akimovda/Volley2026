<?php
// app/Http/Controllers/EventCreateController.php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventGameSetting;
use App\Models\Location;
use App\Models\User;
use App\Jobs\ExpandEventOccurrencesJob;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;


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
        
        $tzGroups  = (array) config('event_timezones.groups', []);
        $tzDefault = (string) config('event_timezones.default', 'Europe/Moscow');

        return view('events.event_management', [
            'tab' => $tab,
            'events' => $events,
            'tzGroups' => $tzGroups,
            'tzDefault' => $tzDefault,
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

        // ✅ trainer prefill label (single OR multiple)
        $trainerLabel = null;
        
        // 1) multiple trainers (new)
        $trainerIds = $prefill['trainer_user_ids'] ?? null;
        if (is_string($trainerIds)) $trainerIds = [$trainerIds];
        if (is_array($trainerIds)) {
            $trainerIds = array_values(array_unique(array_map('intval', $trainerIds)));
            $trainerIds = array_values(array_filter($trainerIds, fn($id) => $id > 0));
        } else {
            $trainerIds = [];
        }
        
        if (count($trainerIds) > 0) {
            $trainers = User::query()
                ->whereIn('id', $trainerIds)
                ->get(['id','name','email'])
                ->keyBy('id');
        
            $labels = [];
            foreach ($trainerIds as $id) {
                if (!isset($trainers[$id])) continue;
                $u = $trainers[$id];
                $labels[] = ($u->name ?: $u->email) . ' (#' . (int)$u->id . ')';
            }
        
            if (count($labels) === 1) {
                $trainerLabel = $labels[0];
            } elseif (count($labels) <= 3) {
                $trainerLabel = implode(', ', $labels);
            } else {
                $trainerLabel = 'Выбрано ' . count($labels) . ' тренеров';
            }
        }
        // 2) fallback legacy single trainer
        elseif (!empty($prefill['trainer_user_id'] ?? null)) {
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

        $tzGroups  = (array) config('event_timezones.groups', []);
        $tzDefault = (string) config('event_timezones.default', 'Europe/Moscow');

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
            'tzGroups'  => $tzGroups,
            'tzDefault' => $tzDefault,
        ]);
    }

    /**
     * ✅ AJAX поиск пользователей для выбора тренера
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

               $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'direction' => ['required', 'in:classic,beach'],
            'format' => ['required', 'in:game,training,training_game,training_pro_am,coach_student,tournament,camp'],
            'allow_registration' => ['required', 'boolean'],
            'age_policy' => ['nullable', 'in:adult,child,any'],

            // ✅ trainers (новое единое поле)
            'trainer_user_ids' => ['nullable', 'array'],
            'trainer_user_ids.*' => ['integer', 'min:1', 'distinct'],

            // ✅ legacy single trainer (оставляем для старых форм)
            'trainer_user_id' => ['nullable', 'integer'],

            // ✅ beach flags
            'is_snow' => ['nullable', 'boolean'],
            'with_minors' => ['nullable', 'boolean'],

            'timezone' => ['required', 'string', 'max:64'],
            'starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at_local' => ['nullable', 'date_format:Y-m-d\TH:i'],

            'classic_level_min' => ['nullable', 'integer', 'min:0', 'max:7'],
            'classic_level_max' => ['nullable', 'integer', 'min:0', 'max:7'],
            'beach_level_min'   => ['nullable', 'integer', 'min:0', 'max:7'],
            'beach_level_max'   => ['nullable', 'integer', 'min:0', 'max:7'],

            // subtype расширяем (classic + beach)
            'game_subtype' => ['nullable', 'in:4x4,4x2,5x1,2x2,3x3'],
            'game_min_players' => ['nullable', 'integer', 'min:1', 'max:99'],
            'game_max_players' => ['nullable', 'integer', 'min:1', 'max:99'],

            'game_allow_girls' => ['nullable', 'boolean'],
            'game_girls_max' => ['nullable', 'integer', 'min:0', 'max:99'],
            'game_libero_mode' => ['nullable', 'in:with_libero,without_libero'],
            'game_has_libero' => ['nullable', 'boolean'],

            'game_positions' => ['nullable', 'array'],
            'game_positions.*' => ['in:setter,outside,opposite,middle,libero'],

            // гендер
            'game_gender_policy' => ['nullable', 'in:only_male,only_female,mixed_open,mixed_limited,mixed_5050'],
            'game_gender_limited_side' => ['nullable', 'in:male,female'],
            'game_gender_limited_max' => ['nullable', 'integer', 'min:0', 'max:99'],
            'game_gender_limited_positions' => ['nullable', 'array'],
            'game_gender_limited_positions.*' => ['in:setter,outside,opposite,middle,libero'],

            'location_id' => ['required', 'integer', 'exists:locations,id'],

            'is_private' => ['nullable', 'boolean'],

            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_rule' => ['nullable', 'string', 'max:255'],
            'recurrence_type' => ['nullable', 'in:daily,weekly,monthly'],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_months' => ['nullable', 'array'],
            'recurrence_months.*' => ['integer', 'min:1', 'max:12'],

            'is_paid' => ['nullable', 'boolean'],
            'price_text' => ['nullable', 'string', 'max:255'],
            'requires_personal_data' => ['nullable', 'boolean'],

            'organizer_id' => ['nullable', 'integer'],

            'remind_registration_enabled' => ['nullable','boolean'],
            'remind_registration_minutes_before' => ['nullable','integer','min:0','max:10080'],
            'show_participants' => ['nullable','boolean'],

            // ✅ cover
            'cover_upload' => ['nullable', 'file', 'image', 'max:5120'],
            'cover_media_id' => ['nullable', 'integer'],

            // ✅ описание мероприятия (HTML из редактора)
            'description_html' => ['nullable', 'string', 'max:50000'],

            // step 2: registration timings (offsets)
            'reg_starts_days_before'     => ['nullable', 'integer', 'min:0', 'max:365'],
            'reg_ends_minutes_before'    => ['nullable', 'integer', 'min:0', 'max:10080'],  // до 7 дней
            'cancel_lock_minutes_before' => ['nullable', 'integer', 'min:0', 'max:10080'],
        ]);

        // ✅ after() ДО validate() — иначе $validator не существует/не сработает
        $validator->after(function ($v) {
            $data   = $v->getData();
            $policy = (string)($data['game_gender_policy'] ?? '');
            $max    = (int)($data['game_max_players'] ?? 0);

            if ($policy === 'mixed_5050') {
                if ($max < 2) {
                    $v->errors()->add('game_max_players', 'Для 50/50 минимум 2 участника.');
                } elseif ($max % 2 !== 0) {
                    $v->errors()->add('game_max_players', 'Для 50/50 макс. участников должен быть чётным.');
                }
            }
        });

        try {
            $data = $validator->validate();
        } catch (ValidationException $e) {
            return $this->backWizard($e->errors());
        }

        $direction = (string)($data['direction'] ?? '');
        $format    = (string)($data['format'] ?? '');
        $agePolicy = (string)($data['age_policy'] ?? 'any');
        if (!in_array($agePolicy, ['adult','child','any'], true)) $agePolicy = 'any';
        
        // ✅ description_html normalize
        if (array_key_exists('description_html', $data)) {
            $data['description_html'] = trim((string)$data['description_html']);
            if ($data['description_html'] === '') $data['description_html'] = null;
        }

        if (!in_array($agePolicy, ['adult','child','any'], true)) $agePolicy = 'any';

        
        // --- trainers normalize (единый массив)
        // 1) берём trainer_user_ids[]
        $trainerIds = $data['trainer_user_ids'] ?? [];
        
        // 2) если старая форма прислала trainer_user_id — тоже подхватим
        $legacyTrainerId = isset($data['trainer_user_id']) ? (int)$data['trainer_user_id'] : 0;
        if ($legacyTrainerId > 0) {
            if (is_array($trainerIds)) $trainerIds[] = $legacyTrainerId;
            else $trainerIds = [$legacyTrainerId];
        }
        
        if (is_string($trainerIds)) $trainerIds = [$trainerIds];
        $trainerIds = is_array($trainerIds) ? array_values(array_unique(array_map('intval', $trainerIds))) : [];
        $trainerIds = array_values(array_filter($trainerIds, fn($id) => $id > 0));
        // ✅ trainers required for these formats (и для классики, и для пляжа)
        $needTrainers = in_array($format, ['training','training_game','training_pro_am','camp'], true);
        
        if ($needTrainers && count($trainerIds) === 0) {
            return $this->backWizard([
                'trainer_user_ids' => ['Выберите минимум одного тренера.'],
            ], 1);
        }
        
        if (count($trainerIds) > 0) {
            $cnt = User::query()->whereIn('id', $trainerIds)->count();
            if ($cnt !== count($trainerIds)) {
                return $this->backWizard([
                    'trainer_user_ids' => ['Некоторые тренеры не найдены.'],
                ], 1);
            }
        }

        // ✅ чистим уровни по направлению
        if (($data['direction'] ?? null) === 'classic') {
            $data['beach_level_min'] = null;
            $data['beach_level_max'] = null;
        } elseif (($data['direction'] ?? null) === 'beach') {
            $data['classic_level_min'] = null;
            $data['classic_level_max'] = null;
        }
        $withMinors = (bool)($data['with_minors'] ?? false);

        if ($direction === 'beach') {
            $allowed = $withMinors ? [1,2,4] : [1,2,3,4,5,6,7];
            foreach (['beach_level_min','beach_level_max'] as $k) {
                if (!is_null($data[$k] ?? null) && !in_array((int)$data[$k], $allowed, true)) {
                    return $this->backWizard([
                        $k => [$withMinors
                            ? 'Для пляжа "с несовершеннолетними" допустимы уровни только 1, 2 или 4.'
                            : 'Для пляжа без несовершеннолетних допустимы уровни 1–7.'],
                    ], 1);
                }
            }
        }

        if ($role === 'staff' && empty($resolvedOrganizerId)) {
            return $this->backWizard([
                'organizer_id' => ['Staff не привязан к organizer — создание мероприятий запрещено.'],
            ], 1);
        }
        if ($role === 'admin') {
            if (empty($organizerId)) {
               return $this->backWizard(['organizer_id' => ['Выберите organizer для мероприятия.'],], 1);
            }
            $org = User::query()->where('id', $organizerId)->where('role', 'organizer')->exists();
            if (!$org) {
               return $this->backWizard(['organizer_id' => ['Неверный organizer_id.'],], 1);
            }
        }

            if (($data['format'] ?? null) === 'coach_student' && ($data['direction'] ?? null) !== 'beach') {
                return $this->backWizard(['format' => ['Формат "Тренер+ученик" доступен только для пляжного волейбола.'],], 1);
            }

            $allowReg = (bool)($data['allow_registration'] ?? false);

// ✅ recurring разрешаем только если allowReg=true (как у вас задумано)
$isRecurring = $allowReg && (bool)($data['is_recurring'] ?? false);

$recRule = trim((string)($data['recurrence_rule'] ?? ''));

// ✅ если recurrence_rule пустой — соберём из recurrence_type/interval/months
if ($isRecurring && $recRule === '') {
    $type     = (string)($data['recurrence_type'] ?? '');
    $interval = (int)($data['recurrence_interval'] ?? 1);

    $months = $data['recurrence_months'] ?? [];
    if (is_string($months)) $months = [$months];
    if (!is_array($months)) $months = [];
    $months = array_values(array_unique(array_map('intval', $months)));
    $months = array_values(array_filter($months, fn($m) => $m >= 1 && $m <= 12));

    $freqMap = [
        'daily'   => 'DAILY',
        'weekly'  => 'WEEKLY',
        'monthly' => 'MONTHLY',
    ];

    if (!isset($freqMap[$type])) {
        return $this->backWizard([
            'recurrence_type' => ['Выбери тип повторения (ежедневно/еженедельно/ежемесячно).'],
        ], 2);
    }

    $parts = [
        'FREQ=' . $freqMap[$type],
        'INTERVAL=' . max(1, $interval),
    ];

    if ($type === 'monthly' && !empty($months)) {
        $parts[] = 'BYMONTH=' . implode(',', $months);
    }

    $recRule = implode(';', $parts);
}

// ✅ Нормализация: если не recurring — rule чистим
if (!$isRecurring) {
    $recRule = '';
}

// ✅ Финальная валидация: recurring => rule обязано быть валидным RRULE
if ($isRecurring) {
    if ($recRule === '' || !preg_match('/\bFREQ=(DAILY|WEEKLY|MONTHLY|YEARLY)\b/i', $recRule)) {
        return $this->backWizard([
            'recurrence_rule' => ['Неверное правило повторения (RRULE). Пример: FREQ=DAILY;INTERVAL=1 или FREQ=WEEKLY.'],
        ], 2);
    }
}

        $isPaid = (bool) ($data['is_paid'] ?? false);
        $priceText = trim((string) ($data['price_text'] ?? ''));
        if ($isPaid && $priceText === '') {
            return $this->backWizard(['price_text' => ['Укажите стоимость/условия оплаты (price_text).'],], 3);

        }
        if (!$isPaid) $priceText = '';

        $locationId = (int) $data['location_id'];
        $location = Location::query()->whereKey($locationId)->first();
        if (!$location) {
            return $this->backWizard(['location_id' => ['Локация не найдена.'],], 2);
        }
        if ($role !== 'admin' && !is_null($location->organizer_id)) {
            return $this->backWizard(['location_id' => ['Организатор может выбирать только локации, созданные админом.'],], 2);
        }

        // Dates
        $tz = (string) $data['timezone'];
        $startsUtc = null;
        $endsUtc = null;
        
        try {
            $startsUtc = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at_local'], $tz)->utc();
        
            if (!empty($data['ends_at_local'])) {
                $endsUtc = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at_local'], $tz)->utc();
                if ($endsUtc->lessThanOrEqualTo($startsUtc)) {
                    return $this->backWizard(['ends_at_local' => ['ends_at должен быть позже starts_at.'],], 2);
                }
            }
        } catch (\Throwable $e) {
            return $this->backWizard([
                'starts_at_local' => ['Неверные дата/время или timezone.'],
                'timezone' => ['Неверные дата/время или timezone.'],
            ], 2);
        }
        
        $nowUtc = CarbonImmutable::now('UTC');
        if ($startsUtc->lessThan($nowUtc)) {
            return $this->backWizard(['starts_at_local' => ['Нельзя создать мероприятие в прошлом. Укажи дату/время в будущем.'],], 2);
        }

        $regStartsUtc = null;
        $regEndsUtc   = null;
        $cancelUntilUtc = null;
        
        if ($startsUtc && $allowReg) {
            $regStartsDaysBefore = isset($data['reg_starts_days_before']) ? (int)$data['reg_starts_days_before'] : 3;
            $regEndsMinutesBefore = isset($data['reg_ends_minutes_before']) ? (int)$data['reg_ends_minutes_before'] : 15;
            $cancelLockMinutesBefore = isset($data['cancel_lock_minutes_before']) ? (int)$data['cancel_lock_minutes_before'] : 60;
        
            // дефолты на всякий случай
            if ($regStartsDaysBefore < 0) $regStartsDaysBefore = 3;
            if ($regEndsMinutesBefore < 0) $regEndsMinutesBefore = 15;
            if ($cancelLockMinutesBefore < 0) $cancelLockMinutesBefore = 60;
        
            $regStartsUtc   = $startsUtc->subDays($regStartsDaysBefore);
            $regEndsUtc     = $startsUtc->subMinutes($regEndsMinutesBefore);
            $cancelUntilUtc = $startsUtc->subMinutes($cancelLockMinutesBefore);
        
            // нормализация/защита от "кривых" значений:
            // 1) конец регистрации должен быть строго ДО старта
            if ($regEndsUtc->greaterThanOrEqualTo($startsUtc)) {
                $regEndsUtc = $startsUtc->subMinutes(15);
            }
            // 2) старт регистрации не позже конца регистрации
            if ($regStartsUtc->greaterThan($regEndsUtc)) {
                $regStartsUtc = $regEndsUtc; // или $regEndsUtc->subMinutes(1) — как тебе удобнее
            }
            // 3) cancelUntil тоже строго ДО старта
            if ($cancelUntilUtc->greaterThanOrEqualTo($startsUtc)) {
                $cancelUntilUtc = $startsUtc->subMinutes(60);
            }
        
            // 4) optional: если старт регистрации получился в прошлом — можно оставить как есть,
            //    или подрезать до "сейчас". Обычно лучше подрезать:
            $nowUtc = CarbonImmutable::now('UTC');
            if ($regStartsUtc->lessThan($nowUtc)) {
                $regStartsUtc = $nowUtc;
                // regEndsUtc не трогаем
                if ($regStartsUtc->greaterThan($regEndsUtc)) {
                    $regStartsUtc = $regEndsUtc; // чтобы start <= end
                }
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

        $isGameClassic = ($direction === 'classic' && $format === 'game');
        $isGameBeach   = ($direction === 'beach'   && $format === 'game');

        if ($isGameClassic) {
            $subtype = (string)($data['game_subtype'] ?? '');

            if ($subtype === '') {
                return $this->backWizard(['game_subtype' => ['Выбери подтип игры (4×4 / 4×2 / 5×1).']], 1);
            }
            
            $defaults = [
                '4x4' => [8, 16],
                '4x2' => [6, 12],
                '5x1' => [6, 12],
            ];
            
            if (!isset($defaults[$subtype])) {
                return $this->backWizard(['game_subtype' => ['Неверный подтип игры.']], 1);
            }
            
            [$defMin, $defMax] = $defaults[$subtype];
            
            // дефолты (если пусто)
            if (empty($data['game_min_players'])) $data['game_min_players'] = $defMin;
            if (empty($data['game_max_players'])) $data['game_max_players'] = $defMax;
            
        } 
            elseif ($isGameBeach) {
            $subtype = (string)($data['game_subtype'] ?? '');
            if (!in_array($subtype, ['2x2','3x3','4x4'], true)) {
                return $this->backWizard(['game_subtype' => ['Выбери подтип игры (2×2 / 3×3 / 4×4).']], 1);
            }
        
            $defaults = [
                '2x2' => [4, 6],
                '3x3' => [6, 12],
                '4x4' => [8, 16],
            ];
            [$defMin, $defMax] = $defaults[$subtype];
        
            if (empty($data['game_min_players'])) $data['game_min_players'] = $defMin;
            if (empty($data['game_max_players'])) $data['game_max_players'] = $defMax;
        
        }else {
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
        // дефолты участников для тренинговых форматов (и классика, и пляж)
        $isTrainingLike = in_array($format, ['training','training_game','training_pro_am','camp'], true);
        if ($isTrainingLike) {
            if (empty($data['game_min_players'])) $data['game_min_players'] = 6;
            if (empty($data['game_max_players'])) $data['game_max_players'] = 12;
        }
        $minPlayers = isset($data['game_min_players']) ? (int)$data['game_min_players'] : null;
        $maxPlayers = isset($data['game_max_players']) ? (int)$data['game_max_players'] : null;
        
        if ($genderPolicy === 'mixed_5050') {
                if (empty($maxPlayers) || $maxPlayers < 2) {
                    return back()->withErrors(['game_max_players' => 'Для 50/50 минимум 2 участника.'])->withInput();
                }
                if ($maxPlayers % 2 !== 0) {
                    return back()->withErrors(['game_max_players' => 'Для 50/50 макс. участников должен быть чётным.'])->withInput();
                }
            }

        
        if (!is_null($minPlayers) && !is_null($maxPlayers) && $maxPlayers < $minPlayers) {
            return $this->backWizard([
                'game_max_players' => ['Макс. участников не может быть меньше Мин. участников.'],
            ], 1);
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
        }
        elseif ($isGameBeach) {
            // пляж: только простые политики, без limited_* и без positions
            $allowedBeachPolicies = ['mixed_open','mixed_5050','only_male','only_female'];
            if ($genderPolicy !== '' && !in_array($genderPolicy, $allowedBeachPolicies, true)) {
                return $this->backWizard([
                    'game_gender_policy' => ['Для пляжа доступны: без ограничений / только мужчины / только девушки / микс 50/50.'],
                ], 1);
            }
        
            $genderLimitedSide = null;
            $genderLimitedMax = null;
            $genderLimitedPositions = null;
        
            // legacy поля пляжу не нужны
            $data['game_allow_girls'] = 0;
            $data['game_girls_max'] = null;
        }
        else {
            $genderPolicy = '';
            $genderLimitedSide = null;
            $genderLimitedMax = null;
            $genderLimitedPositions = null;
        }

        // ✅ приватность (в одном месте)
        $isPrivate = (bool) ($data['is_private'] ?? false);

        // ✅ сюда положим ссылку, чтобы вернуть после commit
        $privateLink = null;
        $event = null;
        $isSnow = (bool)($data['is_snow'] ?? false);
        DB::beginTransaction();
        try {
            $event = new Event();

            $event->title = $data['title'];
            $event->requires_personal_data = (bool) ($data['requires_personal_data'] ?? false);

            $event->classic_level_min = $classicMin;
            if (Schema::hasColumn('events', 'classic_level_max')) {
                $event->classic_level_max = $classicMax;
            }
            $event->beach_level_min = $beachMin;
            if (Schema::hasColumn('events', 'beach_level_max')) {
                $event->beach_level_max = $beachMax;
            }
            if (Schema::hasColumn('events', 'age_policy')) {
                $event->age_policy = $agePolicy; // adult|child|any
            }

            $event->organizer_id = $organizerId;
            $event->location_id = $locationId;

            $event->timezone = $tz;
            $event->starts_at = $startsUtc;
            $event->ends_at = $endsUtc;

            $event->direction = $data['direction'];
            $event->format = $data['format'];
            // ✅ beach flags
            if (Schema::hasColumn('events', 'with_minors')) {
                $event->with_minors = ($direction === 'beach') ? $withMinors : false;
            }
            if (Schema::hasColumn('events', 'is_snow')) {
                $event->is_snow = ($direction === 'beach' && $format === 'game') ? $isSnow : false;
            }

            $event->allow_registration = $allowReg;
            $event->is_paid = $isPaid;
            $event->price_text = $priceText;
            
            // ✅ Step 3: reminder + participants visibility
            if (Schema::hasColumn('events', 'remind_registration_enabled')) {
                $event->remind_registration_enabled = (bool)($data['remind_registration_enabled'] ?? true);
            }
            if (Schema::hasColumn('events', 'remind_registration_minutes_before')) {
                $mins = (int)($data['remind_registration_minutes_before'] ?? 600);
                if ($mins < 0) $mins = 600;
                $event->remind_registration_minutes_before = $mins;
            }
            if (Schema::hasColumn('events', 'show_participants')) {
                $event->show_participants = (bool)($data['show_participants'] ?? true);
            }

            // ✅ description_html (если колонка есть)
            if (Schema::hasColumn('events', 'description_html')) {
                $event->description_html = $data['description_html'] ?? null;
            }


            // ✅ is_private + visibility (в одном месте)
            $event->is_private = $isPrivate;
            if (Schema::hasColumn('events', 'visibility')) {
                $event->visibility = $isPrivate ? 'private' : 'public';
            } else {
                // если visibility нет, оставляем как есть (не трогаем)
                if (property_exists($event, 'visibility')) {
                    $event->visibility = $isPrivate ? 'private' : 'public';
                }
            }

            // ✅ public_token нужен только для приватных
            if ($isPrivate) {
                if (Schema::hasColumn('events', 'public_token') && empty($event->public_token)) {
                    $event->public_token = (string) Str::uuid(); // ✅ UUID под Postgres uuid-колонку
                }
            }

            else {
                // опционально: если сняли приватность — можно очищать токен
                // if (Schema::hasColumn('events', 'public_token')) $event->public_token = null;
            }

            // recurrence_rule кладём в event (как у тебя было)
            if (Schema::hasColumn('events', 'is_recurring')) {
            $event->is_recurring = (bool)$isRecurring;
            }
        
            if (Schema::hasColumn('events', 'recurrence_rule')) {
                $event->recurrence_rule = ($isRecurring && $recRule !== '') ? $recRule : null;
            }

            // время окончания и начала регистрации и время окончания самостоятельной отмены бронирования
            if (Schema::hasColumn('events', 'registration_starts_at')) {
                $event->registration_starts_at = $allowReg ? $regStartsUtc : null;
            }
            if (Schema::hasColumn('events', 'registration_ends_at')) {
                $event->registration_ends_at = $allowReg ? $regEndsUtc : null;
            }
            if (Schema::hasColumn('events', 'cancel_self_until')) {
                $event->cancel_self_until = $allowReg ? $cancelUntilUtc : null;
            }

            // ✅ Сохраняем event, чтобы получить ID и токен
            $event->save();
            // ✅ trainers pivot (если уже сделано в проекте)
            if (method_exists($event, 'trainers') && Schema::hasTable('event_trainers')) {
                if ($needTrainers) $event->trainers()->sync($trainerIds);
                else $event->trainers()->sync([]);
            }
            
            // ✅ legacy single trainer columns — заполним первым тренером (чтобы старые места не ломались)
            $firstTrainerId = $trainerIds[0] ?? 0;
            if (Schema::hasColumn('events', 'trainer_user_id')) {
                $event->trainer_user_id = $firstTrainerId > 0 ? $firstTrainerId : null;
                $event->save();
            } elseif (Schema::hasColumn('events', 'trainer_id')) {
                $event->trainer_id = $firstTrainerId > 0 ? $firstTrainerId : null;
                $event->save();
            }
            // ✅ формируем приватную ссылку ПОСЛЕ save (и вернём её через redirect->with)
            if ((int)($event->is_private ?? 0) === 1 && !empty($event->public_token)) {
                $privateLink = route('events.public', ['token' => $event->public_token]);
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

            $needGameSettings = $isGameClassic || $isGameBeach || $isTrainingLike;

                if ($needGameSettings) {
                    $subtype = $data['game_subtype'] ?? null;
                
                    $positions = [];
                    $liberoMode = null;
                
                    if ($isGameClassic) {
                        $subtypeStr = (string)$subtype;
                
                        if ($subtypeStr === '5x1') {
                            $liberoMode = $data['game_libero_mode'] ?? null;
                            if (!$liberoMode) {
                                $hasLiberoLegacy = (bool)($data['game_has_libero'] ?? false);
                                $liberoMode = $hasLiberoLegacy ? 'with_libero' : 'without_libero';
                            }
                            $liberoMode = $liberoMode ?: 'with_libero';
                        }
                
                        $positions = $this->autoPositionsForClassic($subtypeStr, $liberoMode);
                    }
                
                    // allow_girls/girls_max: только для classic (как раньше)
                    $allowGirls = $isGameClassic ? (bool)($data['game_allow_girls'] ?? false) : false;
                    $girlsMax   = $isGameClassic ? ($data['game_girls_max'] ?? null) : null;
                
                    $egsPayload = [
                        'subtype'     => $subtype,
                        'libero_mode' => $liberoMode,
                        'min_players' => $data['game_min_players'] ?? null,
                        'max_players' => $data['game_max_players'] ?? null,
                        'allow_girls' => $allowGirls,
                        'girls_max'   => $girlsMax,
                        'positions'   => $positions, // beach/training: []
                    ];
                
                    if (Schema::hasColumn('event_game_settings', 'gender_policy')) {
                        $egsPayload['gender_policy'] = ($genderPolicy !== '') ? $genderPolicy : null;
                    }
                
                    // limited_* пишем только для classic, иначе NULL
                    if (Schema::hasColumn('event_game_settings', 'gender_limited_side')) {
                        $egsPayload['gender_limited_side'] = $isGameClassic ? $genderLimitedSide : null;
                    }
                    if (Schema::hasColumn('event_game_settings', 'gender_limited_max')) {
                        $egsPayload['gender_limited_max'] = $isGameClassic ? $genderLimitedMax : null;
                    }
                    if (Schema::hasColumn('event_game_settings', 'gender_limited_positions')) {
                        $egsPayload['gender_limited_positions'] = $isGameClassic ? $genderLimitedPositions : null;
                    }
                
                    EventGameSetting::updateOrCreate(
                        ['event_id' => (int)$event->id],
                        $egsPayload
                    );
                }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Ошибка сохранения мероприятия: ' . $e->getMessage());
        }
            if ($event && (bool)$event->is_recurring && trim((string)$event->recurrence_rule) !== '') {
                ExpandEventOccurrencesJob::dispatch((int)$event->id, 90, 500);
            }

        // ✅ ВАЖНО: редиректим и передаём private_link флешем (чтобы показать на /events)
        $redirect = redirect()->to('/events')
            ->with('status', 'Мероприятие создано ✅');
        
        if (!empty($privateLink)) {
            $redirect->with('private_link', $privateLink);
        }

        return $redirect;
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
            // ✅ multiple trainers prefill (если есть relation)
            if (method_exists($src, 'trainers')) {
                try {
                    $prefill['trainer_user_ids'] = $src->trainers()
                        ->pluck('users.id')
                        ->map(fn($v) => (int)$v)
                        ->values()
                        ->all();
                } catch (\Throwable $e) {
                    // тихо игнорим
                }
            }

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

        private function wizardStepFromErrors(array $errors): int
        {
            $step1 = [
                'organizer_id','title','direction','format','age_policy',
                'trainer_user_ids',
                'trainer_user_id', // оставь на переходный период, чтобы ошибки старого поля тоже в step1 попадали
                'game_subtype','game_min_players','game_max_players',
                'game_libero_mode',
                'game_gender_policy','game_gender_limited_side','game_gender_limited_max','game_gender_limited_positions',
                'classic_level_min','classic_level_max',
                'beach_level_min','beach_level_max',
                'allow_registration',
            ];
        
            $step2 = [
                'timezone','starts_at_local','ends_at_local','location_id',
                'is_recurring','recurrence_type','recurrence_interval','recurrence_months','recurrence_rule',
                'reg_starts_days_before','reg_ends_minutes_before','cancel_lock_minutes_before',
            ];
        
            $step3 = [
                'is_private',
                'is_paid','price_text',
                'requires_personal_data',
                'cover_upload','cover_media_id',
                'description_html',
                'remind_registration_enabled',
                'remind_registration_minutes_before',
                'show_participants',

            ];

        
            foreach ($step3 as $f) if (array_key_exists($f, $errors)) return 3;
            foreach ($step2 as $f) if (array_key_exists($f, $errors)) return 2;
            foreach ($step1 as $f) if (array_key_exists($f, $errors)) return 1;
        
            return 1;
        }
        
        private function backWizard(array $fieldErrors, ?int $forcedStep = null, ?string $flashError = null)
        {
            $step = $forcedStep ?? $this->wizardStepFromErrors($fieldErrors);
        
            $resp = back()
                ->withInput()
                ->withErrors($fieldErrors)
                ->with('wizard_initial_step', $step);
        
            if ($flashError) {
                $resp->with('error', $flashError);
            }
        
            return $resp;
        }
}