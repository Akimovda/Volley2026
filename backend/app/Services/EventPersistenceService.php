<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EventPersistenceService
{

    public function persistEvent(
        Request $request,
        User $user,
        array $data,
        int $organizerId,
        string $agePolicy,
        Location $location,
        string $tz,
        CarbonImmutable $startsUtc,
        ?int $durationSec,
        bool $allowReg,
        array $reg,
        bool $isRecurring,
        string $recRule,
        bool $needTrainers,
        array $trainerIds,
        array $gender,
        array $game,
        string $role,
        string $direction,
        string $format
    ): array {

        return DB::transaction(function () use (
            $request,
            $user,
            $data,
            $organizerId,
            $agePolicy,
            $location,
            $tz,
            $startsUtc,
            $durationSec,
            $allowReg,
            $reg,
            $isRecurring,
            $recRule,
            $needTrainers,
            $trainerIds,
            $gender,
            $game,
            $role,
            $direction,
            $format
        ) {

            $isPrivate = (bool)($data['is_private'] ?? false);
            $isPaid = (bool)($data['is_paid'] ?? false);
            $priceText = $isPaid ? trim((string)($data['price_text'] ?? '')) : '';
            $withMinors = (bool)($data['with_minors'] ?? false);
            $isSnow = (bool)($data['is_snow'] ?? false);

            $event = new Event();

            $event->title = (string)$data['title'];
            $event->organizer_id = $organizerId;
            $event->location_id = (int)$location->id;

            if (Schema::hasColumn('events', 'city_id')) {
                $event->city_id = (int)($location->city_id ?? 0) ?: null;
            }

            $event->timezone = $tz;

            $event->starts_at = $startsUtc;

            if (Schema::hasColumn('events', 'duration_sec')) {
                $event->duration_sec = $durationSec;
            }

            $event->direction = $direction;
            $event->format = $format;

            $event->allow_registration = $allowReg;

            $event->is_paid = $isPaid;
            $event->price_text = $priceText;

            $event->requires_personal_data = (bool)($data['requires_personal_data'] ?? false);

            $event->classic_level_min = $data['classic_level_min'] ?? null;

            if (Schema::hasColumn('events', 'classic_level_max')) {
                $event->classic_level_max = $data['classic_level_max'] ?? null;
            }

            $event->beach_level_min = $data['beach_level_min'] ?? null;

            if (Schema::hasColumn('events', 'beach_level_max')) {
                $event->beach_level_max = $data['beach_level_max'] ?? null;
            }

            if (Schema::hasColumn('events', 'age_policy')) {
                $event->age_policy = $agePolicy;
            }
            if (Schema::hasColumn('events', 'child_age_min')) {
                $event->child_age_min = ($agePolicy === 'child')
                    ? (isset($data['child_age_min']) ? (int)$data['child_age_min'] : null)
                    : null;
            }
            
            if (Schema::hasColumn('events', 'child_age_max')) {
                $event->child_age_max = ($agePolicy === 'child')
                    ? (isset($data['child_age_max']) ? (int)$data['child_age_max'] : null)
                    : null;
            }

            if (Schema::hasColumn('events', 'with_minors')) {
                $event->with_minors = ($direction === 'beach') ? $withMinors : false;
            }

            if (Schema::hasColumn('events', 'is_snow')) {
                $event->is_snow = ($direction === 'beach' && $format === 'game') ? $isSnow : false;
            }

            if (Schema::hasColumn('events', 'remind_registration_enabled')) {
                $event->remind_registration_enabled = (bool)($data['remind_registration_enabled'] ?? true);
            }

            if (Schema::hasColumn('events', 'remind_registration_minutes_before')) {

                $mins = (int)($data['remind_registration_minutes_before'] ?? 600);

                if ($mins < 0) {
                    $mins = 600;
                }

                $event->remind_registration_minutes_before = $mins;
            }

            if (Schema::hasColumn('events', 'show_participants')) {
                $event->show_participants = (bool)($data['show_participants'] ?? true);
            }

            if (Schema::hasColumn('events', 'description_html')) {
                $event->description_html = $data['description_html'] ?? null;
            }

            $event->is_private = $isPrivate;

            if (Schema::hasColumn('events', 'visibility')) {
                $event->visibility = $isPrivate ? 'private' : 'public';
            }

            if (Schema::hasColumn('events', 'public_token') && empty($event->public_token)) {
                $event->public_token = (string)Str::uuid();
            }

            if (Schema::hasColumn('events', 'is_recurring')) {
                $event->is_recurring = (bool)$isRecurring;
            }

            if (Schema::hasColumn('events', 'recurrence_rule')) {
                $event->recurrence_rule = ($isRecurring && trim($recRule) !== '') ? $recRule : null;
            }

            if (Schema::hasColumn('events', 'registration_starts_at')) {
                $event->registration_starts_at = $allowReg ? ($reg['regStartsUtc'] ?? null) : null;
            }

            if (Schema::hasColumn('events', 'registration_ends_at')) {
                $event->registration_ends_at = $allowReg ? ($reg['regEndsUtc'] ?? null) : null;
            }

            if (Schema::hasColumn('events', 'cancel_self_until')) {
                $event->cancel_self_until = $allowReg ? ($reg['cancelUntilUtc'] ?? null) : null;
            }

            $firstTrainerId = (int)($trainerIds[0] ?? 0);

            if (Schema::hasColumn('events', 'trainer_user_id')) {
                $event->trainer_user_id = $firstTrainerId > 0 ? $firstTrainerId : null;
            } elseif (Schema::hasColumn('events', 'trainer_id')) {
                $event->trainer_id = $firstTrainerId > 0 ? $firstTrainerId : null;
            }

            $event->save();

            $privateLink = null;

            if ((bool)($event->is_private ?? false) && !empty($event->public_token)) {
                $privateLink = route('events.public', [
                    'token' => $event->public_token
                ]);
            }

            if ($request->hasFile('cover_upload')) {

                $event->addMediaFromRequest('cover_upload')
                    ->toMediaCollection('cover');

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

            return [
                'event' => $event,
                'privateLink' => $privateLink
            ];
        });
    }

}
