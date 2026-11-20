<?php

namespace App\Console\Commands;

use App\Services\AutoQuorumService;
use Illuminate\Console\Command;

/**
 * Авто увеличение кворума (docs/auto-quorum.md): страховочный проход по будущим датам мероприятий
 * с включённой функцией — расширяет даты, которые пропустили точки вызова (запись/лист ожидания/…).
 */
class AutoQuorumSweep extends Command
{
    protected $signature = 'auto-quorum:sweep {--limit=500 : Максимум дат за проход}';

    protected $description = 'Авто увеличение кворума: проверить будущие даты с включённой функцией и расширить, где набралось';

    public function handle(AutoQuorumService $service): int
    {
        $expanded = $service->sweep(max(1, (int) $this->option('limit')));
        $this->info("expanded: {$expanded}");

        return self::SUCCESS;
    }
}
