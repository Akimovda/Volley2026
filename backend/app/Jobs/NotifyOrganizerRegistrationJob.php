<?php

namespace App\Jobs;

use App\Models\EventOccurrence;
use App\Models\User;
use App\Models\UserNotificationChannel;
use App\Services\Channels\ChannelPublisherFactory;
use App\Services\OrganizerRegistrationNotificationBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyOrganizerRegistrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $occurrenceId,
        public readonly int $playerId,
        public readonly string $type, // 'registered' | 'cancelled'
    ) {}

    public function handle(
        OrganizerRegistrationNotificationBuilder $builder,
        ChannelPublisherFactory $publisherFactory,
    ): void {
        $occurrence = EventOccurrence::with(['event.organizer', 'event.location'])->find($this->occurrenceId);

        if (!$occurrence) {
            return;
        }

        $organizer = $occurrence->event?->organizer;

        if (!$organizer || !$organizer->notify_player_registrations) {
            return;
        }

        $player = User::find($this->playerId);

        if (!$player) {
            return;
        }

        $channels = UserNotificationChannel::query()
            ->where('user_id', (int) $organizer->id)
            ->where('is_verified', true)
            ->get();

        if ($channels->isEmpty()) {
            return;
        }

        foreach ($channels as $channel) {
            try {
                $platform = (string) $channel->platform;
                $message  = $builder->build($occurrence, $player, $this->type, $platform);
                $publisher = $publisherFactory->forChannel($channel);
                $publisher->send((string) $channel->chat_id, $message);
            } catch (\Throwable $e) {
                Log::warning('NotifyOrganizerRegistrationJob: send failed', [
                    'channel_id' => $channel->id,
                    'platform'   => $channel->platform,
                    'type'       => $this->type,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
