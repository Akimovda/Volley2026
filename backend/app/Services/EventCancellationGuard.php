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

			// Если позиция игрока СОВПАДАЕТ с чьей-то заявкой в листе ожидания —
			// вместо обычного $cancelUntil действует отдельный, более поздний
			// (мягкий) дедлайн cancel_self_until_waitlist: отменять безопасно
			// дольше, потому что освободившееся место гарантированно тут же
			// займёт кандидат из очереди (WaitlistService::autoBookNext()). Если
			// совпадения нет — очередь на игрока не влияет, действует обычный
			// $cancelUntil, как если бы очереди не было вовсе.
			$myPosition = EventRegistration::query()
				->where('user_id', $user->id)
				->where('occurrence_id', $occurrence->id)
				->value('position');

			if (!empty($myPosition)) {
				$hasWaitlistDemandForMyPosition = OccurrenceWaitlist::where('occurrence_id', $occurrence->id)
					->get()
					->contains(fn (OccurrenceWaitlist $entry) => $entry->subscribedToPosition($myPosition));

				if ($hasWaitlistDemandForMyPosition) {
					$raw = $occurrence->cancel_self_until_waitlist ?? $event->cancel_self_until_waitlist ?? null;
					if ($raw) {
						$cancelUntil = Carbon::parse($raw, 'UTC');
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