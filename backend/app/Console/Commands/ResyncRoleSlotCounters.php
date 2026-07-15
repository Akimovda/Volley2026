<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResyncRoleSlotCounters extends Command
{
    protected $signature   = 'event-slots:resync {--event_id= : Resync only this event}';
    protected $description = 'DEPRECATED no-op: event_role_slots.taken_slots больше не используется нигде в коде';

    /**
     * Команда выведена из эксплуатации вместе с write-путями taken_slots
     * (см. report_cache_counters_audit_2026-07-16.md) — колонка не читается
     * ни одним потребителем бизнес-логики, чинить нечего. Файл оставлен
     * как no-op (не удалён), т.к. могла быть в чьей-то привычке/кроне.
     * Колонка/команда подлежат удалению отдельной миграцией позже.
     */
    public function handle(): int
    {
        $this->warn('event-slots:resync устарела и ничего не делает: event_role_slots.taken_slots больше не читается кодом (см. report_cache_counters_audit_2026-07-16.md). Команда будет удалена вместе с колонкой.');

        return self::SUCCESS;
    }
}
