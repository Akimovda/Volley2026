<?php

namespace App\Console\Commands;

use App\Models\EventTeamApplication;
use App\Models\EventOccurrence;
use App\Services\UserNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoRejectIncompleteApplicationsCommand extends Command
{
    protected $signature = 'tournaments:auto-reject-incomplete-applications {--dry-run}';
    protected $description = 'Автоотклоняет неполные заявки команд после окончания приёма заявок ближайшего повторения';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $now = Carbon::now('UTC');
        $processed = 0;

        $apps = EventTeamApplication::query()
            ->where('status', 'incomplete')
            ->with(['team.event', 'team.captain'])
            ->get();

        foreach ($apps as $app) {
            $team = $app->team;
            if (!$team || !$team->event) continue;

            // Берём дедлайн текущего/предстоящего повторения, к которому привязана команда.
            // Если у команды нет occurrence_id — fallback к ближайшему occurrence события.
            $occurrence = null;
            if ($team->occurrence_id) {
                $occurrence = EventOccurrence::find($team->occurrence_id);
            }
            if (!$occurrence) {
                $occurrence = EventOccurrence::query()
                    ->where('event_id', $team->event_id)
                    ->whereNull('cancelled_at')
                    ->where('starts_at', '>=', $now)
                    ->orderBy('starts_at')
                    ->first();
            }

            $deadline = $occurrence?->registration_ends_at;
            if (!$deadline) continue;

            $deadline = Carbon::parse($deadline);
            if ($now->lessThan($deadline)) continue; // ещё не время

            $this->info("Auto-rejecting application #{$app->id} (team #{$team->id} «{$team->name}»)");

            if ($dry) { $processed++; continue; }

            DB::transaction(function () use ($app, $team) {
                $app->update([
                    'status' => 'rejected',
                    'reviewed_at' => now(),
                    'rejection_reason' => 'Состав не собран к окончанию приёма заявок',
                    'decision_comment' => 'Автоматическое отклонение',
                ]);
                $team->update(['status' => 'rejected']);

                DB::table('event_team_member_audits')->insert([
                    'event_team_id' => $team->id,
                    'user_id' => null,
                    'performed_by_user_id' => null,
                    'action' => 'application_auto_rejected',
                    'old_value' => json_encode(['status' => 'incomplete'], JSON_UNESCAPED_UNICODE),
                    'new_value' => json_encode(['status' => 'rejected'], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ]);
            });

            // Уведомление капитану
            try {
                $teamUrl = route('tournamentTeams.show', [$team->event_id, $team->id]);
                app(UserNotificationService::class)->create(
                    userId: (int) $team->captain_user_id,
                    type: 'tournament_application_auto_rejected',
                    title: __('events.tapp_auto_rejected_title'),
                    body: __('events.tapp_auto_rejected_body', ['team' => $team->name, 'event' => $team->event->title])
                        . "\n\n" . __('events.tapp_action_open', ['url' => $teamUrl]),
                    payload: [
                        'event_id' => $team->event_id,
                        'team_id' => $team->id,
                        'application_id' => $app->id,
                        'team_name' => $team->name,
                        'event_title' => $team->event->title,
                        'button_text' => __('events.tapp_btn_open_team'),
                        'button_url' => $teamUrl,
                    ],
                    channels: ['in_app', 'telegram', 'vk', 'max']
                );
            } catch (\Throwable $e) {
                report($e);
            }

            $processed++;
        }

        $this->info("Processed: {$processed}");
        return self::SUCCESS;
    }
}
