<?php

namespace App\Console\Commands;

use App\Models\EventOccurrence;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromptActivityRecording extends Command
{
    protected $signature = 'activity:prompt-recording';
    protected $description = 'Отправить пуш «Записать активность?» участникам текущих мероприятий';

    public function handle(PushNotificationService $push): int
    {
        $afterMin = (int) config('activity.prompt_after_min', 5);
        $graceMin = (int) config('activity.prompt_grace_min', 35);

        // Occurrences, которые стартовали в окне [now-grace .. now-after]
        $occurrences = EventOccurrence::query()
            ->where('starts_at', '<=', now()->subMinutes($afterMin))
            ->where('starts_at', '>=', now()->subMinutes($graceMin))
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->pluck('id');

        if ($occurrences->isEmpty()) {
            return self::SUCCESS;
        }

        $recordingOpen = (bool) config('activity.recording_open');
        $consentVersion = (string) config('activity.consent_version');
        $sent = 0;

        foreach ($occurrences as $occurrenceId) {
            // Активные регистрации (не отменены, не резерв)
            $userIds = DB::table('event_registrations')
                ->where('occurrence_id', $occurrenceId)
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->where('position', '!=', 'reserve')
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                // Гейт: admin ИЛИ recording_open
                if (!$recordingOpen) {
                    $isAdmin = DB::table('users')
                        ->where('id', $userId)
                        ->whereIn('role', ['admin', 'superadmin'])
                        ->exists();
                    if (!$isAdmin) {
                        continue;
                    }
                }

                // Есть хотя бы одно устройство
                $hasDevice = DB::table('athlete_devices')
                    ->where('user_id', $userId)
                    ->exists();
                if (!$hasDevice) {
                    continue;
                }

                // Дано согласие
                $hasConsent = DB::table('user_consents')
                    ->where('user_id', $userId)
                    ->where('type', 'health_activity')
                    ->where('document_version', $consentVersion)
                    ->exists();
                if (!$hasConsent) {
                    continue;
                }

                // Дедуп: вставляем только если ещё не слали
                $inserted = DB::table('activity_record_prompts')->insertOrIgnore([
                    'occurrence_id' => $occurrenceId,
                    'user_id'       => $userId,
                    'sent_at'       => now(),
                ]);

                if (!$inserted) {
                    continue;
                }

                $url = 'https://volleyplay.club/activity/record?occurrence=' . $occurrenceId . '&auto=1';

                $result = $push->send(
                    userId: (int) $userId,
                    title:  'VolleyPlay',
                    body:   __('activity.prompt_body'),
                    data:   [
                        'type'          => 'activity_record',
                        'occurrence_id' => $occurrenceId,
                        'button_url'    => $url,
                    ],
                );

                Log::warning('activity:prompt-recording sent', [
                    'occurrence_id' => $occurrenceId,
                    'user_id'       => $userId,
                    'push_result'   => $result,
                ]);

                $sent++;
            }
        }

        Log::warning('activity:prompt-recording finished', [
            'occurrences_checked' => $occurrences->count(),
            'pushes_sent'         => $sent,
        ]);

        return self::SUCCESS;
    }
}
