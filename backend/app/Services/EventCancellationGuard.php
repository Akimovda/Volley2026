<?php
	// app/Services/EventCancellationGuard.php
	namespace App\Services;
	
	use App\Models\User;
	use App\Models\EventOccurrence;
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