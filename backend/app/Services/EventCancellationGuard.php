<?php
	// app/Services/EventCancellationGuard.php
	namespace App\Services;
	
	use App\Models\User;
	use App\Models\EventOccurrence;
	use App\Models\EventRegistration;
	use App\Models\OccurrenceWaitlist;
	use Illuminate\Support\Carbon;
	
	final class EventCancellationGuard
	{
		public function check(?User $user, EventOccurrence $occurrence): GuardResult
		{
			if (!$user) {
				return GuardResult::deny('Нужно войти в аккаунт.');
			}
			
			$event = $occurrence->event;
			if (!$event) {
				return GuardResult::deny('Событие не найдено.');
			}
			
			$nowUtc = Carbon::now('UTC');
			$tz = $occurrence->timezone ?: ($event->timezone ?: 'UTC');
			
			/*
				|-----------------------------------------
				| 1. EVENT ALREADY STARTED
				|-----------------------------------------
			*/
			if (!empty($occurrence->starts_at)) {
				$startsAt = Carbon::parse($occurrence->starts_at, 'UTC');
				if ($nowUtc->greaterThanOrEqualTo($startsAt)) {
					$local = $startsAt->copy()->setTimezone($tz)->format('d.m.Y H:i');
					return GuardResult::deny("Мероприятие уже началось ({$local}).");
				}
			}
			
			/*
				|-----------------------------------------
				| 2. CANCEL WINDOW (occurrence → event)
				|-----------------------------------------
			*/
			$cancelUntil = null;

			if (!empty($occurrence->cancel_self_until)) {
				$cancelUntil = Carbon::parse($occurrence->cancel_self_until, 'UTC');
				} elseif (!empty($event->cancel_self_until)) {
				$cancelUntil = Carbon::parse($event->cancel_self_until, 'UTC');
			}

			// Более строгий (ранний) дедлайн "при наличии листа ожидания" применяется
			// НЕ ко всем подряд, а только к игроку, чья текущая позиция реально
			// востребована кем-то в очереди (OccurrenceWaitlist.positions). Если в
			// очереди никого нет — или там ждут только других позиций — действует
			// обычный $cancelUntil, как будто очереди нет вовсе.
			$myPosition = EventRegistration::query()
				->where('user_id', $user->id)
				->where('occurrence_id', $occurrence->id)
				->value('position');

			$cancelUntilWaitlist = null;
			if (!empty($myPosition)) {
				$hasWaitlistDemandForMyPosition = OccurrenceWaitlist::where('occurrence_id', $occurrence->id)
					->get()
					->contains(fn (OccurrenceWaitlist $entry) => $entry->subscribedToPosition($myPosition));

				if ($hasWaitlistDemandForMyPosition) {
					$raw = $occurrence->cancel_self_until_waitlist ?? $event->cancel_self_until_waitlist ?? null;
					if ($raw) {
						$cancelUntilWaitlist = Carbon::parse($raw, 'UTC');
						// Строже основного лимита — значит раньше по времени
						if ($cancelUntil === null || $cancelUntilWaitlist->lessThan($cancelUntil)) {
							$cancelUntil = $cancelUntilWaitlist;
						}
					}
				}
			}

			if ($cancelUntil && $nowUtc->greaterThanOrEqualTo($cancelUntil)) {
				$local = $cancelUntil->copy()->setTimezone($tz)->format('d.m.Y H:i');
				return GuardResult::deny("Отмена записи недоступна после {$local}.");
			}
			
			/*
				|-----------------------------------------
				| 3. OK
				|-----------------------------------------
			*/
			return GuardResult::allow();
		}
	}	