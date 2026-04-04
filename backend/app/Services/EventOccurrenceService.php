<?php
	
	namespace App\Services;
	
	use App\Models\Event;
	use App\Models\EventOccurrence;
	use Carbon\CarbonImmutable;
	use Illuminate\Support\Facades\Schema;
	
	class EventOccurrenceService
	{
		/*
			|--------------------------------------------------------------------------
			| PARSE DATES
			|--------------------------------------------------------------------------
		*/
		
		public function parseAndAssertDates(array $data, string $tz): array
		{
			try {
				$startsLocal = CarbonImmutable::parse($data['starts_at_local'], $tz);
				$startsUtc   = $startsLocal->utc();
				} catch (\Throwable $e) {
				return [
                'errors' => [
				'starts_at_local' => ['Некорректная дата начала']
                ]
				];
			}
			
			$errors = [];
			
			$durationSec = (int)($data['duration_sec'] ?? 0);
			
			$minDuration = 300;
			$maxDuration = 10 * 24 * 3600;
			
			if ($durationSec < $minDuration) {
				$errors['duration_sec'][] = 'Длительность слишком мала';
			}
			
			if ($durationSec > $maxDuration) {
				$errors['duration_sec'][] = 'Длительность не может превышать 10 дней';
			}
			
			if ($errors) {
				return ['errors' => $errors];
			}
			
			return [
            'startsUtc'   => $startsUtc,
            'durationSec' => $durationSec,
			];
		}
		
		/*
			|--------------------------------------------------------------------------
			| RECURRENCE RULE
			|--------------------------------------------------------------------------
		*/
		
		public function normalizeRecurrenceRule(array $data, bool $allowReg, string $tz): array
		{
			$isRecurring = (bool)($data['is_recurring'] ?? false);
			
			if (!$isRecurring) {
				return [
                'isRecurring' => false,
                'recRule' => '',
                'errors' => [],
				];
			}
			
			$errors = [];
			
			$freqMap = [
            'daily'   => 'DAILY',
            'weekly'  => 'WEEKLY',
            'monthly' => 'MONTHLY',
			];
			
			$type = $data['recurrence_type'] ?? null;
			
			if (!$type || !isset($freqMap[$type])) {
				$errors['recurrence_type'] = ['Некорректный тип повторов'];
			}
			
			$interval = max(1, (int)($data['recurrence_interval'] ?? 1));
			
			$byDay = [];
			
			if ($type === 'weekly') {
				
				$weekdays = $data['recurrence_weekdays'] ?? [];
				
				$map = [
                1 => 'MO',
                2 => 'TU',
                3 => 'WE',
                4 => 'TH',
                5 => 'FR',
                6 => 'SA',
                7 => 'SU',
				];
				
				foreach ((array)$weekdays as $d) {
					if (isset($map[(int)$d])) {
						$byDay[] = $map[(int)$d];
					}
				}
				
				if (empty($byDay)) {
					$errors['recurrence_weekdays'] = [
                    'Выбери хотя бы один день недели'
					];
				}
			}
			
			$untilPart = null;
			$countPart = null;
			
			$endType = $data['recurrence_end_type'] ?? 'none';
			
if ($endType === 'until') {

    $untilInput = $data['recurrence_end_until'] ?? $data['recurrence_until'] ?? null;

    if (empty($untilInput)) {
        $errors['recurrence_end_until'] = [
            'Укажи дату окончания повторов'
        ];
    } else {
        try {
            $untilLocal = CarbonImmutable::parse(
                $untilInput,
                $tz
            )->endOfDay();
						
						$untilUtc = $untilLocal
                        ->setTimezone('UTC')
                        ->format('Ymd\THis\Z');
						
						$untilPart = 'UNTIL=' . $untilUtc;
						
						} catch (\Throwable $e) {
						
						$errors['recurrence_end_until'] = [
                        'Некорректная дата окончания'
						];
					}
				}
			}
			
			if ($endType === 'count') {
				
				$count = (int)(
				$data['recurrence_end_count']
				?? $data['recurrence_count']
				?? 0
				);
				
				if ($count <= 0) {
					
					$errors['recurrence_end_count'] = [
                    'Количество повторов должно быть больше 0'
					];
					
					} else {
					
					$countPart = 'COUNT=' . $count;
					
				}
			}
			
			if (!empty($errors)) {
				return [
                'isRecurring' => false,
                'recRule' => '',
                'errors' => $errors,
				];
			}
			
			$parts = [
            'FREQ=' . $freqMap[$type],
            'INTERVAL=' . $interval,
			];
			
			if (!empty($byDay)) {
				$parts[] = 'BYDAY=' . implode(',', $byDay);
			}
			
			if ($untilPart) {
				$parts[] = $untilPart;
				} elseif ($countPart) {
				$parts[] = $countPart;
			}
			
			return [
            'isRecurring' => true,
            'recRule' => implode(';', $parts),
            'errors' => [],
			];
		}
		
		/*
			|--------------------------------------------------------------------------
			| REGISTRATION WINDOWS
			|--------------------------------------------------------------------------
		*/
		
		public function buildRegistrationWindows(
        ?CarbonImmutable $startsUtc,
        bool $allowReg,
        array $data
		): array {
			
			$regStartsUtc = null;
			$regEndsUtc = null;
			$cancelUntilUtc = null;
			
			if (!$startsUtc || !$allowReg) {
				return compact(
                'regStartsUtc',
                'regEndsUtc',
                'cancelUntilUtc'
				);
			}
			
			$regStartsDaysBefore = (int)($data['reg_starts_days_before'] ?? 3);
			$regEndsMinutesBefore = (int)($data['reg_ends_minutes_before'] ?? 15);
			$cancelLockMinutesBefore = (int)($data['cancel_lock_minutes_before'] ?? 60);
			
			$regStartsUtc = $startsUtc->subDays($regStartsDaysBefore);
			$regEndsUtc = $startsUtc->subMinutes($regEndsMinutesBefore);
			$cancelUntilUtc = $startsUtc->subMinutes($cancelLockMinutesBefore);
			
			return compact(
            'regStartsUtc',
            'regEndsUtc',
            'cancelUntilUtc'
			);
		}
		
		/*
			|--------------------------------------------------------------------------
			| CREATE FIRST OCCURRENCE
			|--------------------------------------------------------------------------
		*/
		
		public function createFirstOccurrence(
        Event $event,
        int $durationSec,
        string $agePolicy,
        bool $isRecurring,
        array $data
		): void {
			
			if (!Schema::hasTable('event_occurrences') || empty($event->starts_at)) {
				return;
			}
			
			$gs = $event->relationLoaded('gameSettings')
            ? $event->gameSettings
            : $event->gameSettings()->first();
			
			$startUtc = CarbonImmutable::parse((string)$event->starts_at, 'UTC');
			
			$uniq = "event:{$event->id}:{$startUtc->format('YmdHis')}";
			
			$payload = [
            'starts_at' => $startUtc,
            'duration_sec' => $durationSec,
            'timezone' => $event->timezone ?: 'UTC',
            'allow_registration' => (bool)($event->allow_registration ?? false),
            'max_players' => $gs?->max_players ?? null,
			];
			
			if (Schema::hasColumn('event_occurrences', 'location_id')) {
				$payload['location_id'] = $event->location_id ?? null;
			}
			
			foreach ([
            'classic_level_min',
            'classic_level_max',
            'beach_level_min',
            'beach_level_max',
            'registration_starts_at',
            'registration_ends_at',
            'cancel_self_until',
			] as $col) {
				
				if (Schema::hasColumn('event_occurrences', $col)) {
					$payload[$col] = $data[$col] ?? null;
				}
				
			}
			
			if (Schema::hasColumn('event_occurrences', 'age_policy')) {
				$payload['age_policy'] = $agePolicy ?: 'any';
			}
			
			if (Schema::hasColumn('event_occurrences', 'is_snow')) {
				$payload['is_snow'] = (bool)($event->is_snow ?? false);
			}
			
			EventOccurrence::query()->updateOrCreate(
            [
			'event_id' => (int)$event->id,
			'uniq_key' => $uniq,
            ],
            $payload
			);
		}
	}	