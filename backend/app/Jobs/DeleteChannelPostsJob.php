<?php

namespace App\Jobs;

use App\Services\PublishOccurrenceAnnouncementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Сценарий B: occurrence/событие уже физически удалены (каскадом снесло
 * event_channel_messages). $messages собран ДО DELETE — см. вызывающий код
 * (EventManagementController::destroyOccurrence/update/safeForceDeleteEvent) —
 * job не зависит от того, жива ли ещё строка в БД к моменту выполнения.
 */
class DeleteChannelPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(public array $messages) {}

    public function handle(PublishOccurrenceAnnouncementService $service): void
    {
        if (empty($this->messages)) {
            return;
        }

        $service->deletePosts($this->messages);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('DeleteChannelPostsJob failed', [
            'messages_count' => count($this->messages),
            'error'          => $e->getMessage(),
        ]);
    }
}
