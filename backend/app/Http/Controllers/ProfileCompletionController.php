<?php
	
	namespace App\Http\Controllers;
	
	use App\Models\User;
	use App\Models\City;
	use App\Services\ProfileUpdateGuard;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
	
	final class ProfileCompletionController extends Controller
	{
		use AuthorizesRequests;
		
		/**
			* Показ формы профиля
		*/
		public function show(Request $request)
		{
			// -------------------------------------------------
			// 1. Actor
			// -------------------------------------------------
			$actor = $request->user();
			abort_unless($actor, 403);
			
			// -------------------------------------------------
			// 2. Target
			// -------------------------------------------------
			$targetId = (int) $request->integer('user_id');
			$target   = $actor;
			
			if ($targetId > 0 && $targetId !== $actor->id) {
				// обычный пользователь — нельзя
				if (!$actor->isAdmin() && !$actor->isOrganizer()) {
					return redirect()->route('profile.complete');
				}
				
				$target = User::query()->findOrFail($targetId);
			}
			
			// -------------------------------------------------
			// 3. Mode для Blade (через Guard)
			// -------------------------------------------------
			$mode = ProfileUpdateGuard::viewMode($actor, $target);
			
			if ($mode === null) {
				abort(403);
			}
			
			// -------------------------------------------------
			// 4. required / section / event_id / missing / return_to
			// -------------------------------------------------
			$requiredRaw = (string) $request->query('required', '');
			$section     = (string) $request->query('section', '');
			$eventId     = $request->query('event_id');

			// Контекстное заполнение: missing поля + URL возврата на мероприятие
			$missingRaw = (string) $request->query('missing', '');
			$returnTo   = (string) $request->query('return_to', '');

			$missingKeys = collect(explode(',', $missingRaw))
				->map(fn ($s) => trim((string) $s))
				->filter()
				->unique()
				->values()
				->all();

			if (!empty($returnTo)) {
				$request->session()->put('profile_return_to', $returnTo);
			}

			if (!empty($eventId)) {
				$request->session()->put('pending_event_join', (int) $eventId);
			}

			$required = collect(explode(',', $requiredRaw))
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->values();

			if ($required->isEmpty() && $section !== '') {
				$map = config('profile.sections', []);
				$required = collect($map[$section] ?? []);
			}

			$requiredKeys = $required->unique()->values()->all();

			// Редирект только когда все значимые поля (включая дополнительные) заполнены
			$allOptionalFilled = !empty($target->birth_date)
				&& !empty($target->gender)
				&& !empty($target->patronymic)
				&& !empty($target->city_id);

			if (empty($requiredKeys) && empty($section) && empty($eventId) && empty($missingKeys)
				&& $target->id === $actor->id && $target->isProfileComplete() && $allOptionalFilled
				&& !ProfileUpdateGuard::isAdmin($actor) && !ProfileUpdateGuard::isOrganizer($actor)) {
				return redirect('/user/profile')->with('status', 'Ваш профиль уже заполнен ✅');
			}
			$request->session()->put('pending_profile_required', $requiredKeys);
			
			// -------------------------------------------------
			// 5. Города
			// -------------------------------------------------
			$cities = City::query()
            ->whereIn('country_code', ['RU', 'KZ', 'UZ'])
            ->orderBy('country_code')
            ->orderByRaw('region nulls last')
            ->orderBy('name')
            ->get(['id', 'name', 'region', 'country_code']);
			
			// -------------------------------------------------
			// 6. Pending organizer request
			// -------------------------------------------------
			/*
				$hasPendingRequest = DB::table('organizer_requests')
				->where('user_id', $target->id)
				->where('status', 'pending')
				->exists();
			*/
			return view('profile.complete', [
            'actor'             => $actor,
            'target'            => $target,
            'mode'              => $mode,
            'actorId'           => $actor->id,
            'targetId'          => $target->id,
            'requiredKeys'      => $requiredKeys,
            'missingKeys'       => $missingKeys,
            'returnTo'          => $returnTo ?: ($request->session()->get('profile_return_to', '')),
            'eventId'           => $eventId,
            'section'           => $section,
            'cities'            => $cities,
            'canEditProtected'  => ProfileUpdateGuard::isAdmin($actor) || (ProfileUpdateGuard::isOrganizer($actor) && $actor->id === $target->id),
			//     'hasPendingRequest' => $hasPendingRequest,
			]);
		}
		
		/**
			* Сохранение профиля
		*/
		public function update(Request $request)
		{
			// -------------------------------------------------
			// 1. Actor
			// -------------------------------------------------
			$actor = $request->user();
			abort_unless($actor, 403);
			
			// -------------------------------------------------
			// 2. Target (ВАЖНО!)
			// -------------------------------------------------
			$targetId = (int) $request->input('user_id');
			$target = $actor;
			
			if ($targetId > 0 && $targetId !== $actor->id) {
				// доступ к чужому профилю
				$target = User::query()->findOrFail($targetId);
				
				// дополнительная защита (опционально)
				$this->authorize('update', $target);
			}
			
			// -------------------------------------------------
			// 3. Guard
			// -------------------------------------------------
			$result = \App\Services\ProfileUpdateGuard::check(
			$actor,
			$target,
			$request->all()
			);
			
			if (!$result->allowed) {
				return back()->withErrors([
				'profile' => $result->message,
				]);
			}
			
			// -------------------------------------------------
			// 4. Save
			// -------------------------------------------------
			$wasComplete = !is_null($target->profile_completed_at);

			$target->fill($result->data);

			if ($target->isProfileComplete()) {
				if (is_null($target->profile_completed_at)) {
					$target->profile_completed_at = now();
				}
			} else {
				$target->profile_completed_at = null;
			}

			$isFirstCompletion = !$wasComplete && !is_null($target->profile_completed_at) && $target->id === $actor->id;

			$target->save();

			// -------------------------------------------------
			// 5. Redirect
			// -------------------------------------------------
			$returnTo = (string) $request->input('return_to', '');
			if (empty($returnTo)) {
				$returnTo = (string) $request->session()->pull('profile_return_to', '');
			} else {
				$request->session()->forget('profile_return_to');
			}

			if ($isFirstCompletion) {
				if (!empty($returnTo)) {
					return redirect($returnTo)
						->with('success', 'Профиль заполнен! Теперь вы можете записаться на мероприятие.');
				}
				return redirect()->route('events.index')
					->with('success', 'Профиль заполнен! Добро пожаловать 🏐');
			}

			if (!empty($returnTo)) {
				return redirect($returnTo)
					->with('success', 'Профиль обновлён. Попробуйте записаться снова.');
			}

			return redirect()
				->route('profile.complete', ['user_id' => $target->id])
				->with('success', 'Профиль обновлён.');
		}
	}	