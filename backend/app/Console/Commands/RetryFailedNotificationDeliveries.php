<?php

namespace App\Console\Commands;

use App\Services\NotificationDeliverySender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryFailedNotificationDeliveries extends Command
{
    protected $signature = 'notifications:retry-failed {--dry-run : Показать кандидатов без повторной отправки}';
    protected $description = 'Повторить неудачные доставки уведомлений (транзиентные ошибки: cURL/сеть/5xx)';

    public function handle(NotificationDeliverySender $sender): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $maxAgeHours = (int) config('notifications.retry_max_age_hours', 6);
        $cutoff = now()->subHours($maxAgeHours);

        $query = fn () => DB::table('notification_deliveries')
            ->where('status', 'failed')
            ->where('is_retryable', true)
            ->where('attempts', '<', 3)
            ->where('next_retry_at', '<=', now())
            ->where('created_at', '>', $cutoff);

        $candidates = $query()->get(['id', 'channel', 'user_id', 'attempts', 'created_at']);

        if ($candidates->isEmpty()) {
            $this->info('Нет доставок, готовых к повтору.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("DRY RUN — будет повторено: {$candidates->count()} (потолок возраста {$maxAgeHours}ч)");
            foreach ($candidates as $row) {
                $this->line(sprintf(
                    '  #%d channel=%s user=%d attempts=%d created_at=%s',
                    $row->id,
                    $row->channel,
                    $row->user_id,
                    $row->attempts,
                    $row->created_at
                ));
            }
            return self::SUCCESS;
        }

        $retried = 0;
        foreach ($candidates as $row) {
            try {
                $sender->sendById((int) $row->id, isRetry: true);
                $retried++;
            } catch (\Throwable $e) {
                // sendById() сам ловит ошибки канала внутри — сюда попадём только
                // при чём-то совсем неожиданном (например обрыв соединения с БД).
                Log::warning('RetryFailedNotificationDeliveries: unexpected error', [
                    'delivery_id' => $row->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // Отдельно — те, что вышли за потолок возраста, но ещё формально retryable:
        // они никогда не будут подхвачены этим запросом снова (created_at не меняется),
        // отмечаем в лог, чтобы не потерялись молча.
        $staleCount = DB::table('notification_deliveries')
            ->where('status', 'failed')
            ->where('is_retryable', true)
            ->where('attempts', '<', 3)
            ->where('created_at', '<=', $cutoff)
            ->count();

        if ($staleCount > 0) {
            Log::warning('RetryFailedNotificationDeliveries: доставки старше потолка возраста больше не ретраятся', [
                'count'          => $staleCount,
                'max_age_hours'  => $maxAgeHours,
            ]);
        }

        $this->info("Повторено попыток: {$retried} из {$candidates->count()}.");

        return self::SUCCESS;
    }
}
