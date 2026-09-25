<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Heartbeat для Uptime Kuma: сам факт успешного выполнения этого джоба в общей
 * очереди подтверждает, что воркер жив и разбирает очередь. Лёгкий, tries=1 —
 * повторные попытки не имеют смысла (следующий heartbeat придёт через 5 минут).
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        $url = config('services.kuma.queue');

        if (empty($url)) {
            return;
        }

        try {
            Http::timeout(5)->get($url);
        } catch (\Throwable $e) {
            // Мониторинг не должен ронять очередь — любые сетевые ошибки глотаем.
        }
    }
}
