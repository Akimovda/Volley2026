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

        if (!(bool)$event->allow_registration) {
            return back()->with('error', 'Регистрация на это мероприятие выключена.');
        }

        // ✅ gender: в БД сейчас m/f/NULL — нормализуем в male/female
        $userGender = $this->normalizeGender($user->gender ?? null);
        if (!$userGender) {
            return redirect()->to('/profile/complete')
                ->with('error', 'Укажите пол в профиле (m/f), чтобы записаться.');
        }

        // position (может быть пустым в legacy режимах)
        $position = trim((string)$request->input('position', ''));

        $gs = $event->gameSettings;
        $subtype = (string)($gs->subtype ?? '');
        $liberoMode = (string)($gs->libero_mode ?? '');
        $maxPlayers = (int)($gs->max_players ?? 0);

        // positions из settings (cast=array, но подстрахуемся)
        $positions = $gs?->positions;
        if (is_string($positions)) {
            $decoded = json_decode($positions, true);
            $positions = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($positions)) $positions = [];
        $positions = array_values(array_unique(array_map('strval', $positions)));

        $hasPositionRegistration = ($maxPlayers > 0) && !empty($positions);

        // ✅ gender policy (как в EventGameSetting)
        $genderPolicy = (string)($gs->gender_policy ?? 'mixed_open'); // mixed_open|only_male|only_female|mixed_limited
        $genderLimitedSide = (string)($gs->gender_limited_side ?? ''); // male|female
        $genderLimitedMax = is_null($gs->gender_limited_max) ? null : (int)$gs->gender_limited_max;

        $genderLimitedPositions = $gs->gender_limited_positions;
        if (is_string($genderLimitedPositions)) {
            $decoded = json_decode($genderLimitedPositions, true);
            $genderLimitedPositions = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($genderLimitedPositions)) $genderLimitedPositions = [];
        $genderLimitedPositions = array_values(array_unique(array_map('strval', $genderLimitedPositions)));

        // ✅ базовая политика допуска по полу
        if ($genderPolicy === 'only_male' && $userGender !== 'male') {
            return back()->with('error', 'Это мероприятие только для мужчин.');
        }
        if ($genderPolicy === 'only_female' && $userGender !== 'female') {
            return back()->with('error', 'Это мероприятие только для женщин.');
        }

        // ✅ если позиционная запись включена — позиция обязательна и валидна
        if ($hasPositionRegistration) {
            if ($position === '') {
                return back()->with('error', 'Выберите позицию.');
            }
            if (!in_array($position, $positions, true)) {
                return back()->with('error', 'Некорректная позиция.');
            }
        } else {
            // если позиционной записи нет — позицию игнорируем
            $position = '';
        }

        // ✅ mixed_limited: ограничения для "ограничиваемой" стороны
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
            // 🔒 ВАЖНО: из-за уникального (occurrence_id,user_id) нельзя "вставить заново",
            // если у пользователя уже есть запись (даже отмененная). Значит:
            // - если активная -> "уже записаны"
            // - если отмененная -> "восстановить" (update) вместо insert
            $existing = EventRegistration::query()
                ->where('user_id', (int)$user->id)
                ->where('occurrence_id', (int)$occurrence->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $this->isActiveRegistration($existing)) {
                DB::commit();
                return redirect()->to('/events')->with('status', 'Вы уже записаны ✅');
            }

            // ✅ общий лимит (max_players)
            if ($maxPlayers > 0) {
                $totalQ = EventRegistration::query()
                    ->where('occurrence_id', (int)$occurrence->id);
                $this->applyActiveScope($totalQ);

                // ⚠️ Postgres: нельзя FOR UPDATE с агрегатами => лочим строки и считаем в PHP
                $totalTaken = (int)$totalQ->select('id')->lockForUpdate()->get()->count();

                if ($totalTaken >= $maxPlayers) {
                    DB::rollBack();
                    return back()->with('error', 'Свободных мест больше нет.');
                }
            }

            // ✅ проверка вместимости по позиции (по командам)
            if ($hasPositionRegistration) {
                $perTeam = $this->perTeamPositionCounts($subtype, $liberoMode);
                $teamSize = $this->teamSize($subtype, $liberoMode);

                // количество команд по общему лимиту
                $teamsCount = ($teamSize > 0) ? intdiv(max(0, $maxPlayers), $teamSize) : 0;
                if ($teamsCount <= 0) $teamsCount = 1;

                $perTeamCnt = (int)($perTeam[$position] ?? 0);
                $posCapacity = ($perTeamCnt > 0) ? ($perTeamCnt * $teamsCount) : $maxPlayers;

                $takenQ = EventRegistration::query()
                    ->where('occurrence_id', (int)$occurrence->id)
                    ->where('position', $position);
                $this->applyActiveScope($takenQ);

                $taken = (int)$takenQ->select('id')->lockForUpdate()->get()->count();

                if ($posCapacity > 0 && $taken >= $posCapacity) {
                    DB::rollBack();
                    return back()->with('error', 'Мест на эту позицию больше нет.');
                }
            }

            // ✅ gender_limited_max (лимит мест для ограничиваемого пола)
            if ($isLimitedSideUser && !is_null($genderLimitedMax)) {
                // users.gender в БД: m/f (и иногда может быть male/female) — учитываем оба
                $rawGenders = ($userGender === 'male') ? ['m', 'male'] : ['f', 'female'];

                $genderQ = EventRegistration::query()
                    ->join('users', 'users.id', '=', 'event_registrations.user_id')
                    ->where('event_registrations.occurrence_id', (int)$occurrence->id)
                    ->whereIn('users.gender', $rawGenders);

                $this->applyActiveScope($genderQ, 'event_registrations.');

                // ⚠️ Postgres: lock + count нельзя — лочим ids
                $limitedTaken = (int)$genderQ->select('event_registrations.id')->lockForUpdate()->get()->count();

                if ($limitedTaken >= $genderLimitedMax) {
                    DB::rollBack();
                    return back()->with('error', 'Достигнут лимит мест по гендерному ограничению.');
                }
            }

            // ✅ создаём/восстанавливаем регистрацию
            if ($existing) {
                // восстановление отмененной
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
                // на всякий — поддерживаем event_id
                if (Schema::hasColumn('event_registrations', 'event_id')) {
                    $existing->event_id = (int)$event->id;
                }
                $existing->save();
            } else {
                $reg = new EventRegistration();
                $reg->user_id = (int)$user->id;

                // event_id обязателен в вашей схеме
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

            // если вдруг прилетела unique по occurrence+user — покажем человечески
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

        DB::beginTransaction();
        try {
            $q = EventRegistration::query()
                ->where('user_id', (int)$user->id);

            if (Schema::hasColumn('event_registrations', 'occurrence_id')) {
                $q->where('occurrence_id', (int)$occurrence->id);
            } else {
                // fallback (на всякий)
                if ($event && Schema::hasColumn('event_registrations', 'event_id')) {
                    $q->where('event_id', (int)$event->id);
                }
            }

            $reg = $q->lockForUpdate()->first();
            if ($reg) {
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
            }

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

        $startUtc = Carbon::parse($event->starts_at, 'UTC');
        $uniq = "event:{$event->id}:{$startUtc->format('YmdHis')}";

        return EventOccurrence::query()->updateOrCreate(
            ['uniq_key' => $uniq],
            [
                'event_id'  => (int)$event->id,
                'starts_at' => $startUtc,
                'ends_at'   => $event->ends_at ? Carbon::parse($event->ends_at, 'UTC') : null,
                'timezone'  => $event->timezone ?: 'UTC',
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

        if ($subtype === '5x1') {
            return ($liberoMode === 'with_libero') ? 7 : 6;
        }

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
}
