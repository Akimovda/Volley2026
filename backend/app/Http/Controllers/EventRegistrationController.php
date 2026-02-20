<?php
// app/Http/Controllers/EventRegistrationController.php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventRegistrationController extends Controller
{
    /**
     * Legacy: POST /events/{event}/join
     * Чтобы не ломать старые кнопки — записываем в "первый occurrence" события.
     */
    public function store(Request $request, Event $event)
    {
        $occ = $this->getOrCreateFirstOccurrenceForEvent($event);
        if (!$occ) return back()->with('error', 'Не удалось найти occurrence для события.');
        return $this->storeOccurrence($request, $occ);
    }

    /**
     * Legacy: DELETE /events/{event}/leave
     * Отписка от "первого occurrence".
     */
    public function destroy(Request $request, Event $event)
    {
        $occ = $this->getOrCreateFirstOccurrenceForEvent($event);
        if (!$occ) return back()->with('error', 'Не удалось найти occurrence для события.');
        return $this->destroyOccurrence($request, $occ);
    }

    /**
     * NEW: POST /occurrences/{occurrence}/join
     */
    public function storeOccurrence(Request $request, EventOccurrence $occurrence)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $occurrence->load(['event.gameSettings']);
        $event = $occurrence->event;
        if (!$event) return back()->with('error', 'Событие не найдено.');

        $sf = $this->schemaFlags();
        $hasUserBirthDate = $sf['users.birth_date'];

        // --- AGE POLICY (occ snapshot -> fallback event) ---
        $agePolicy = $this->effectiveAgePolicy($occurrence, $event, $sf);

        if ($agePolicy !== 'any') {
            if (!$hasUserBirthDate || empty($user->birth_date)) {
                return redirect()->to('/profile/complete')
                    ->with('error', 'Чтобы записаться, укажите дату рождения в профиле.');
            }

            $startUtc = !empty($occurrence->starts_at)
                ? Carbon::parse($occurrence->starts_at, 'UTC')
                : Carbon::now('UTC');

            $birth = Carbon::parse($user->birth_date)->startOfDay();
            $age = $birth->diffInYears($startUtc);

            if ($agePolicy === 'adult' && $age < 18) {
                return back()->with('error', 'Это мероприятие только для взрослых (18+).');
            }
            if ($agePolicy === 'child' && $age >= 18) {
                return back()->with('error', 'Это мероприятие только для детей (до 18).');
            }
        }

        // --- allow_registration (occ override) ---
        $allowReg = $this->effectiveAllowRegistration($occurrence, $event, $sf);
        if (!$allowReg) {
            return back()->with('error', 'Регистрация на это мероприятие выключена.');
        }

        // --- time helpers ---
        $nowUtc  = Carbon::now('UTC');
        $eventTz = (string)($occurrence->timezone ?: ($event->timezone ?: 'UTC'));

        $fmtLocal = function ($dt) use ($eventTz) {
            return Carbon::parse($dt, 'UTC')->setTimezone($eventTz)->format('d.m.Y H:i') . " ({$eventTz})";
        };
        $fmtUtc = function ($dt) {
            return Carbon::parse($dt, 'UTC')->format('d.m.Y H:i') . ' (UTC)';
        };

        // --- started gate (для записи) ---
        if (!empty($occurrence->starts_at) && $nowUtc->greaterThanOrEqualTo(Carbon::parse($occurrence->starts_at, 'UTC'))) {
            $startLocal = $fmtLocal($occurrence->starts_at);
            return back()->with('error', "Мероприятие уже началось ({$startLocal}) — запись невозможна.");
        }

        // --- registration window (ONLY occurrence snapshot) ---
        if ($sf['occ.registration_starts_at'] && !empty($occurrence->registration_starts_at)) {
            $regStartsUtc = Carbon::parse($occurrence->registration_starts_at, 'UTC');
            if ($nowUtc->lessThan($regStartsUtc)) {
                $local = $fmtLocal($occurrence->registration_starts_at);
                $utc   = $fmtUtc($occurrence->registration_starts_at);
                return back()->with('error', "Регистрация ещё не началась. Старт: {$local} / {$utc}.");
            }
        }

        if ($sf['occ.registration_ends_at'] && !empty($occurrence->registration_ends_at)) {
            $regEndsUtc = Carbon::parse($occurrence->registration_ends_at, 'UTC');
            if ($nowUtc->greaterThanOrEqualTo($regEndsUtc)) {
                $local = $fmtLocal($occurrence->registration_ends_at);
                $utc   = $fmtUtc($occurrence->registration_ends_at);
                return back()->with('error', "Регистрация уже закрыта. Закрытие: {$local} / {$utc}.");
            }
        }

        // --- gender ---
        $userGender = $this->normalizeGender($user->gender ?? null);
        if (!$userGender) {
            return redirect()->to('/profile/complete')
                ->with('error', 'Укажите пол в профиле (М/Ж), чтобы записаться.');
        }

        // position (может быть пустым в legacy режимах)
        $position = trim((string)$request->input('position', ''));

        // --- game settings safe defaults ---
        $gs = $event->gameSettings; // может быть null
        $subtype    = (string)($gs?->subtype ?? '');
        $liberoMode = (string)($gs?->libero_mode ?? '');
        $maxPlayers = (int)($gs?->max_players ?? 0);

        $positions = $gs?->positions;
        if (is_string($positions)) {
            $decoded = json_decode($positions, true);
            $positions = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($positions)) $positions = [];
        $positions = array_values(array_unique(array_map('strval', $positions)));

        $hasPositionRegistration = ($maxPlayers > 0) && !empty($positions);

        $genderPolicy      = (string)($gs?->gender_policy ?? 'mixed_open'); // mixed_open|only_male|only_female|mixed_limited
        $genderLimitedSide = (string)($gs?->gender_limited_side ?? '');     // male|female
        $genderLimitedMax  = is_null($gs?->gender_limited_max) ? null : (int)$gs->gender_limited_max;

        $genderLimitedPositions = $gs?->gender_limited_positions;
        if (is_string($genderLimitedPositions)) {
            $decoded = json_decode($genderLimitedPositions, true);
            $genderLimitedPositions = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($genderLimitedPositions)) $genderLimitedPositions = [];
        $genderLimitedPositions = array_values(array_unique(array_map('strval', $genderLimitedPositions)));

        // --- base gender policy gates ---
        if ($genderPolicy === 'only_male' && $userGender !== 'male') {
            return back()->with('error', 'Это мероприятие только для мужчин.');
        }
        if ($genderPolicy === 'only_female' && $userGender !== 'female') {
            return back()->with('error', 'Это мероприятие только для женщин.');
        }

        // --- position required if enabled ---
        if ($hasPositionRegistration) {
            if ($position === '') return back()->with('error', 'Выберите позицию.');
            if (!in_array($position, $positions, true)) return back()->with('error', 'Некорректная позиция.');
        } else {
            $position = '';
        }

        $isLimitedSideUser = ($genderPolicy === 'mixed_limited')
            && in_array($genderLimitedSide, ['male', 'female'], true)
            && ($userGender === $genderLimitedSide);

        if ($isLimitedSideUser && $hasPositionRegistration && !empty($genderLimitedPositions)) {
            if (!in_array($position, $genderLimitedPositions, true)) {
                return back()->with('error', 'Вам недоступна выбранная позиция по гендерным ограничениям.');
            }
        }

        DB::beginTransaction();
        try {
            $existing = EventRegistration::query()
                ->where('user_id', (int)$user->id)
                ->where('occurrence_id', (int)$occurrence->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $this->isActiveRegistration($existing)) {
                DB::commit();
                return redirect()->to('/events')->with('status', 'Вы уже записаны ✅');
            }

            // --- total max players ---
            if ($maxPlayers > 0) {
                $totalQ = EventRegistration::query()->where('occurrence_id', (int)$occurrence->id);
                $this->applyActiveScope($totalQ);

                $totalTaken = (int)$totalQ->select('id')->lockForUpdate()->get(['id'])->count();
                if ($totalTaken >= $maxPlayers) {
                    DB::rollBack();
                    return back()->with('error', 'Свободных мест больше нет.');
                }
            }
            // --- mixed_5050: лимит 50/50 по полу (без новых колонок) ---
            if ($genderPolicy === 'mixed_5050' && $maxPlayers > 0) {
                // maxPlayers должен быть чётным — но на всякий случай страхуемся
                if ($maxPlayers % 2 !== 0) {
                    DB::rollBack();
                    return back()->with('error', 'Ошибка настроек: для 50/50 max_players должен быть чётным.');
                }
            
                $limitPerGender = intdiv($maxPlayers, 2);
            
                // считаем активных по полу (через join users, как у тебя в mixed_limited)
                $rawGenders = ($userGender === 'male') ? ['m', 'male'] : ['f', 'female'];
            
                $q5050 = EventRegistration::query()
                    ->join('users', 'users.id', '=', 'event_registrations.user_id')
                    ->where('event_registrations.occurrence_id', (int)$occurrence->id)
                    ->whereIn('users.gender', $rawGenders);
            
                $this->applyActiveScope($q5050, 'event_registrations.');
            
                $takenSameGender = (int)$q5050
                    ->select('event_registrations.id')
                    ->lockForUpdate()
                    ->get(['event_registrations.id'])
                    ->count();
            
                if ($takenSameGender >= $limitPerGender) {
                    DB::rollBack();
                    $label = ($userGender === 'male') ? 'мужчин' : 'женщин';
                    return back()->with('error', "Лимит 50/50 для {$label} заполнен ({$limitPerGender}).");
                }
            }

            // --- per-position capacity ---
            if ($hasPositionRegistration) {
                $perTeam = $this->perTeamPositionCounts($subtype, $liberoMode);
                $teamSize = $this->teamSize($subtype, $liberoMode);

                $teamsCount = ($teamSize > 0) ? intdiv(max(0, $maxPlayers), $teamSize) : 0;
                if ($teamsCount <= 0) $teamsCount = 1;

                $perTeamCnt = (int)($perTeam[$position] ?? 0);
                $posCapacity = ($perTeamCnt > 0) ? ($perTeamCnt * $teamsCount) : $maxPlayers;

                $takenQ = EventRegistration::query()
                    ->where('occurrence_id', (int)$occurrence->id)
                    ->where('position', $position);
                $this->applyActiveScope($takenQ);

                $taken = (int)$takenQ->select('id')->lockForUpdate()->get(['id'])->count();
                if ($posCapacity > 0 && $taken >= $posCapacity) {
                    DB::rollBack();
                    return back()->with('error', 'Мест на эту позицию больше нет.');
                }
            }

            // --- gender limited max ---
            if ($isLimitedSideUser && !is_null($genderLimitedMax)) {
                $rawGenders = ($userGender === 'male') ? ['m', 'male'] : ['f', 'female'];

                $genderQ = EventRegistration::query()
                    ->join('users', 'users.id', '=', 'event_registrations.user_id')
                    ->where('event_registrations.occurrence_id', (int)$occurrence->id)
                    ->whereIn('users.gender', $rawGenders);

                $this->applyActiveScope($genderQ, 'event_registrations.');

                $limitedTaken = (int)$genderQ->select('event_registrations.id')->lockForUpdate()->get(['event_registrations.id'])->count();
                if ($limitedTaken >= $genderLimitedMax) {
                    DB::rollBack();
                    return back()->with('error', 'Достигнут лимит мест по гендерному ограничению.');
                }
            }

            // --- create/restore registration ---
            if ($existing) {
                if (Schema::hasColumn('event_registrations', 'position')) {
                    $existing->position = $position !== '' ? $position : null;
                }
                if (Schema::hasColumn('event_registrations', 'status')) {
                    $existing->status = 'confirmed';
                }
                if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
                    $existing->is_cancelled = false;
                }
                if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
                    $existing->cancelled_at = null;
                }
                if (Schema::hasColumn('event_registrations', 'event_id')) {
                    $existing->event_id = (int)$event->id;
                }
                $existing->save();
            } else {
                $reg = new EventRegistration();
                $reg->user_id = (int)$user->id;

                if (Schema::hasColumn('event_registrations', 'event_id')) {
                    $reg->event_id = (int)$event->id;
                }
                if (Schema::hasColumn('event_registrations', 'occurrence_id')) {
                    $reg->occurrence_id = (int)$occurrence->id;
                }
                if (Schema::hasColumn('event_registrations', 'position')) {
                    $reg->position = $position !== '' ? $position : null;
                }
                if (Schema::hasColumn('event_registrations', 'status')) {
                    $reg->status = 'confirmed';
                }
                if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
                    $reg->is_cancelled = false;
                }
                if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
                    $reg->cancelled_at = null;
                }
                $reg->save();
            }

            DB::commit();
            return redirect()->to('/events')->with('status', 'Записались ✅');
        } catch (\Throwable $e) {
            DB::rollBack();

            $msg = $e->getMessage();
            if (str_contains($msg, 'uniq_occ_user') || str_contains($msg, 'duplicate key value')) {
                return redirect()->to('/events')->with('status', 'Вы уже записаны ✅');
            }

            return back()->with('error', 'Ошибка записи: ' . $e->getMessage());
        }
    }

    /**
     * NEW: DELETE /occurrences/{occurrence}/leave
     */
    public function destroyOccurrence(Request $request, EventOccurrence $occurrence)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $occurrence->load(['event']);
        $event = $occurrence->event;

        $sf = $this->schemaFlags();

        $nowUtc  = Carbon::now('UTC');
        $eventTz = (string)($occurrence->timezone ?: ($event?->timezone ?: 'UTC'));

        // 0) started gate — ВСЕГДА
        if (!empty($occurrence->starts_at)) {
            $startsAtUtc = Carbon::parse($occurrence->starts_at, 'UTC');
            if ($nowUtc->greaterThanOrEqualTo($startsAtUtc)) {
                $startLocal = $startsAtUtc->copy()->setTimezone($eventTz)->format('d.m.Y H:i') . " ({$eventTz})";
                return back()->with('error', "Отмена невозможна: мероприятие уже началось ({$startLocal}).");
            }
        }

        // 1) cancel_self_until (occ snapshot -> fallback event)
        $cancelUntil = $this->resolveCancelUntil($occurrence, $event, $sf);

        // 2) deadline gate (строгое <)
        if ($cancelUntil && $nowUtc->greaterThanOrEqualTo($cancelUntil)) {
            $lockLocal = $cancelUntil->copy()->setTimezone($eventTz)->format('d.m.Y H:i') . " ({$eventTz})";
            $lockUtc   = $cancelUntil->format('d.m.Y H:i') . ' (UTC)';
            return back()->with('error', "Отмена записи недоступна. Дедлайн был: {$lockLocal} / {$lockUtc}.");
        }

        DB::beginTransaction();
        try {
            $q = EventRegistration::query()->where('user_id', (int)$user->id);

            if (Schema::hasColumn('event_registrations', 'occurrence_id')) {
                $q->where('occurrence_id', (int)$occurrence->id);
            } else {
                if ($event && Schema::hasColumn('event_registrations', 'event_id')) {
                    $q->where('event_id', (int)$event->id);
                }
            }

            $reg = $q->lockForUpdate()->first();

            if (!$reg) {
                DB::commit();
                return redirect()->to('/events')->with('status', 'Вы не были записаны на это мероприятие.');
            }

            if (Schema::hasColumn('event_registrations', 'status')) {
                $reg->status = 'cancelled';
            }
            if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
                $reg->is_cancelled = true;
            }
            if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
                $reg->cancelled_at = now();
            }

            $reg->save();

            DB::commit();
            return redirect()->to('/events')->with('status', 'Запись отменена ✅');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Ошибка отмены: ' . $e->getMessage());
        }
    }

    private function getOrCreateFirstOccurrenceForEvent(Event $event): ?EventOccurrence
    {
        if (!Schema::hasTable('event_occurrences')) return null;

        $occ = EventOccurrence::query()
            ->where('event_id', (int)$event->id)
            ->orderBy('starts_at', 'asc')
            ->first();

        if ($occ) return $occ;
        if (!$event->starts_at) return null;

        $sf = $this->schemaFlags();

        $startUtc = Carbon::parse($event->starts_at, 'UTC');
        $uniq = "event:{$event->id}:{$startUtc->format('YmdHis')}";

        return EventOccurrence::query()->updateOrCreate(
            ['uniq_key' => $uniq],
            [
                'event_id'  => (int)$event->id,
                'starts_at' => $startUtc,
                'ends_at'   => $event->ends_at ? Carbon::parse($event->ends_at, 'UTC') : null,
                'timezone'  => $event->timezone ?: 'UTC',

                'cancel_self_until'       => $sf['occ.cancel_self_until'] ? ($event->cancel_self_until ?? null) : null,
                'registration_starts_at'  => $sf['occ.registration_starts_at'] ? ($event->registration_starts_at ?? null) : null,
                'registration_ends_at'    => $sf['occ.registration_ends_at'] ? ($event->registration_ends_at ?? null) : null,

                'age_policy' => $sf['occ.age_policy'] ? ($event->age_policy ?? 'any') : null,
                'is_snow'    => $sf['occ.is_snow'] ? (bool)($event->is_snow ?? false) : null,
            ]
        );
    }

    /**
     * Активна ли регистрация (учитываем ваши поля status/is_cancelled/cancelled_at).
     */
    private function isActiveRegistration(EventRegistration $reg): bool
    {
        if (Schema::hasColumn('event_registrations', 'is_cancelled') && (bool)$reg->is_cancelled) return false;
        if (Schema::hasColumn('event_registrations', 'cancelled_at') && $reg->cancelled_at) return false;

        if (Schema::hasColumn('event_registrations', 'status')) {
            $st = (string)($reg->status ?? '');
            if ($st !== '' && $st !== 'confirmed') return false;
        }

        if (Schema::hasColumn('event_registrations', 'deleted_at') && $reg->getAttribute('deleted_at')) return false;

        return true;
    }

    /**
     * Применить условия "активных" регистраций к Query Builder.
     * $prefix = '' или 'event_registrations.' для join-ов.
     */
    private function applyActiveScope($q, string $prefix = ''): void
    {
        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $q->where($prefix . 'is_cancelled', false);
        }
        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $q->whereNull($prefix . 'cancelled_at');
        }
        if (Schema::hasColumn('event_registrations', 'status')) {
            $q->where($prefix . 'status', 'confirmed');
        }
        if (Schema::hasColumn('event_registrations', 'deleted_at')) {
            $q->whereNull($prefix . 'deleted_at');
        }
    }

    /**
     * Нормализуем gender из БД: m/f/male/female -> male/female.
     */
    private function normalizeGender(?string $g): ?string
    {
        $g = strtolower(trim((string)$g));
        if ($g === '') return null;

        $map = [
            'm' => 'male',
            'male' => 'male',
            'man' => 'male',
            'f' => 'female',
            'female' => 'female',
            'woman' => 'female',
        ];

        return $map[$g] ?? null;
    }

    private function teamSize(string $subtype, string $liberoMode): int
    {
        $subtype = trim($subtype);
        $liberoMode = trim($liberoMode);

        if ($subtype === '4x4') return 4;
        if ($subtype === '4x2') return 6;
        if ($subtype === '5x1') return ($liberoMode === 'with_libero') ? 7 : 6;

        return 0;
    }

    /**
     * Кол-во мест по позициям В ОДНОЙ КОМАНДЕ (по твоему ТЗ).
     * Возвращает: ['setter'=>1,'outside'=>2,...]
     */
    private function perTeamPositionCounts(string $subtype, string $liberoMode): array
    {
        $subtype = trim($subtype);
        $liberoMode = trim($liberoMode);

        if ($subtype === '4x4') {
            return ['setter' => 1, 'outside' => 2, 'opposite' => 1];
        }

        if ($subtype === '4x2') {
            return ['setter' => 1, 'outside' => 4];
        }

        if ($subtype === '5x1') {
            $base = ['setter' => 1, 'outside' => 2, 'opposite' => 1, 'middle' => 2];
            if ($liberoMode === 'with_libero') $base['libero'] = 1;
            return $base;
        }

        return [];
    }

    /**
     * ---------- ВЫНЕСЕННЫЕ "effective" МЕТОДЫ ----------
     */

    private function effectiveAllowRegistration(EventOccurrence $occ, Event $event, array $sf): bool
    {
        $allow = (bool)($event->allow_registration ?? false);

        if ($sf['occ.allow_registration'] && !is_null($occ->allow_registration)) {
            $allow = (bool)$occ->allow_registration;
        }

        return $allow;
    }

    private function effectiveAgePolicy(EventOccurrence $occ, Event $event, array $sf): string
    {
        $agePolicy = 'any';

        if ($sf['occ.age_policy'] && !empty($occ->age_policy)) {
            $agePolicy = (string)$occ->age_policy;
        } elseif (!empty($event->age_policy)) {
            $agePolicy = (string)$event->age_policy;
        }

        $agePolicy = in_array($agePolicy, ['adult', 'child', 'any'], true) ? $agePolicy : 'any';

        return $agePolicy;
    }

    private function resolveCancelUntil(EventOccurrence $occ, ?Event $event, array $sf): ?Carbon
    {
        if ($sf['occ.cancel_self_until'] && !empty($occ->cancel_self_until)) {
            return Carbon::parse($occ->cancel_self_until, 'UTC');
        }

        if (!empty($event?->cancel_self_until)) {
            return Carbon::parse($event->cancel_self_until, 'UTC');
        }

        return null;
    }

    /**
     * Кэш наличия колонок/таблиц. Хватает на один php-process (FPM воркер).
     */
    private function schemaFlags(): array
    {
        static $cache = null;
        if (is_array($cache)) return $cache;

        $hasOccTable = Schema::hasTable('event_occurrences');

        $cache = [
            'users.birth_date' => Schema::hasColumn('users', 'birth_date'),

            'occ.allow_registration'     => $hasOccTable && Schema::hasColumn('event_occurrences', 'allow_registration'),
            'occ.age_policy'            => $hasOccTable && Schema::hasColumn('event_occurrences', 'age_policy'),
            'occ.cancel_self_until'     => $hasOccTable && Schema::hasColumn('event_occurrences', 'cancel_self_until'),
            'occ.registration_starts_at'=> $hasOccTable && Schema::hasColumn('event_occurrences', 'registration_starts_at'),
            'occ.registration_ends_at'  => $hasOccTable && Schema::hasColumn('event_occurrences', 'registration_ends_at'),
            'occ.is_snow'               => $hasOccTable && Schema::hasColumn('event_occurrences', 'is_snow'),
        ];

        return $cache;
    }
}
