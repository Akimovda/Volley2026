<?php

namespace App\Http\Controllers;

use App\Models\EventOccurrence;
use App\Services\WaitlistService;
use App\Services\EventRegistrationGuard;
use Illuminate\Http\Request;

class OccurrenceWaitlistController extends Controller
{
    public function store(Request $request, EventOccurrence $occurrence): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        // Проверяем что мероприятие не началось
        if ($occurrence->starts_at && now('UTC')->gte($occurrence->starts_at)) {
            return back()->with('error', 'Мероприятие уже началось.');
        }

        // Турниры не поддерживают резерв
        $regMode = (string)($occurrence->event->registration_mode ?? 'single');
        if (in_array($regMode, ['team_classic', 'team_beach'], true)) {
            return back()->with('error', 'Резерв недоступен для турниров.');
        }

        // Нельзя встать в резерв если уже записан в состав (включая запасные места)
        $alreadyRegistered = \App\Models\EventRegistration::where('user_id', $user->id)
            ->where('occurrence_id', $occurrence->id)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->exists();
        if ($alreadyRegistered) {
            return back()->with('error', 'Вы уже записаны на это мероприятие. Сначала отмените запись, чтобы встать в резерв.');
        }

        // Проверяем возраст, уровень и прочие условия допуска.
        // Гендерное окно пропускаем — записаться в очередь до открытия окна разрешено;
        // autoBookNext запустится только когда окно откроется (ProcessWaitlistGenderWindows).
        $eligibility = app(EventRegistrationGuard::class)->checkEligibility($user, $occurrence, skipGenderWindow: true);
        if (!$eligibility->allowed) {
            return back()->with('error', implode(' ', $eligibility->errors));
        }

        $positions = $request->input('positions', []);
        if (!is_array($positions)) $positions = [];

        // Если гендерное окно закрыто — только разрешённые позиции
        if ($eligibility->meta['gender_window_closed'] ?? false) {
            $allowedPos = $eligibility->meta['gender_window_positions'] ?? [];
            $invalidPos = array_filter($positions, fn($p) => !in_array($p, $allowedPos, true));
            if (!empty($invalidPos)) {
                $readableAllowed = implode(', ', array_map('position_name', $allowedPos));
                return back()->with('error', 'Пока регистрация не открылась — доступны только позиции: ' . $readableAllowed . '.');
            }
            // Если позиции не выбраны — автоматически ставим разрешённые
            if (empty($positions)) {
                $positions = $allowedPos;
            }
        }

        // Проверяем лимит резерва (не больше max_players)
        $maxPlayers = (int)($occurrence->event->gameSettings->max_players ?? 0);
        if ($maxPlayers > 0) {
            $waitlistCount = \App\Models\OccurrenceWaitlist::query()
                ->where('occurrence_id', $occurrence->id)
                ->count();
            if ($waitlistCount >= $maxPlayers) {
                return back()->with('error', 'Резерв заполнен.');
            }
        }

        app(WaitlistService::class)->join($occurrence, $user, $positions);

        return back()->with('status', 'Вы записаны в резерв! Когда освободится подходящее место, мы автоматически переведём вас в основной состав.');
    }

    public function destroy(Request $request, EventOccurrence $occurrence): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        app(WaitlistService::class)->leave($occurrence, $user);

        return back()->with('status', 'Вы покинули резерв.');
    }
}
