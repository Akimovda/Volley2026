<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationDeliveryJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendPendingNotifications extends Command
{
    protected $signature = 'notifications:send-pending {--limit=100}';
    protected $description = 'Отправить pending/failed уведомления из notification_deliveries';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $rows = DB::table('notification_deliveries')
            ->whereIn('status', ['pending', 'failed'])
            ->whereIn('channel', ['telegram', 'vk'])
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);

        foreach ($rows as $row) {
            SendNotificationDeliveryJob::dispatch((int) $row->id)->onQueue('default');
        }

        $this->info('Dispatched: ' . $rows->count());

        return self::SUCCESS;
    }
}
