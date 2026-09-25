<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Одноразовая команда — Этап 4 из report/account_links_audit_2026-09-25.md.
 * Восстанавливает 4 vk_id, потерянных 2026-09-25 при прогоне
 * users:clear-deleted-provider-fields (обнулила provider id без построчного
 * лога), извлечённых из локального утреннего дампа
 * /home/appuser/pgbackup/dumps/volleyplay_20260925.dump (снят в 09:24, за 7
 * часов до очистки в 16:24). Решение пользователя 2026-09-25: восстанавливать
 * ТОЛЬКО в account_links (source='merge'), НЕ в users.vk_id — 3 google_id из
 * того же дампа (self-delete, не merge) сознательно НЕ восстанавливаются и
 * нигде не хранятся. Удалить класс отдельным коммитом после применения на проде.
 */
class BackfillMergedAccountLinks extends Command
{
    protected $signature = 'users:backfill-merged-account-links
        {--force : Применить (без флага — только показать, что будет сделано)}
        {--actor= : ID администратора для admin_audits (обязателен вместе с --force)}';

    protected $description = 'Восстановить 4 vk_id, потерянных при merge() до фикса от 2026-09-25, в account_links';

    private const ROWS = [
        ['secondary_id' => 469, 'primary_id' => 470, 'provider' => 'vk', 'provider_user_id' => '23753777'],
        ['secondary_id' => 396, 'primary_id' => 565, 'provider' => 'vk', 'provider_user_id' => '262749383'],
        ['secondary_id' => 457, 'primary_id' => 348, 'provider' => 'vk', 'provider_user_id' => '1113473378'],
        ['secondary_id' => 624, 'primary_id' => 620, 'provider' => 'vk', 'provider_user_id' => '1116790959'],
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        foreach (self::ROWS as $row) {
            $exists = DB::table('account_links')
                ->where('provider', $row['provider'])
                ->where('provider_user_id', $row['provider_user_id'])
                ->exists();

            $this->line(sprintf(
                '%s:%s (secondary #%d → primary #%d) — %s',
                $row['provider'],
                $row['provider_user_id'],
                $row['secondary_id'],
                $row['primary_id'],
                $exists ? 'уже есть в account_links, пропуск' : 'будет вставлена'
            ));
        }

        if (!$force) {
            $this->warn('DRY RUN — ничего не изменено. Повторите с --force (и --actor=<id>) для применения.');
            return self::SUCCESS;
        }

        $actorId = (int) $this->option('actor');
        if ($actorId <= 0 || !User::whereKey($actorId)->exists()) {
            $this->error('Укажите --actor=<id существующего пользователя> — admin_audits.actor_user_id обязателен (NOT NULL).');
            return self::FAILURE;
        }

        $inserted = 0;

        DB::transaction(function () use ($actorId, &$inserted) {
            foreach (self::ROWS as $row) {
                $exists = DB::table('account_links')
                    ->where('provider', $row['provider'])
                    ->where('provider_user_id', $row['provider_user_id'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('account_links')->insert([
                    'user_id'             => $row['primary_id'],
                    'provider'            => $row['provider'],
                    'provider_user_id'    => $row['provider_user_id'],
                    'source'              => 'merge',
                    'merged_from_user_id' => $row['secondary_id'],
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $inserted++;
            }

            if ($inserted > 0) {
                DB::table('admin_audits')->insert([
                    'admin_user_id' => $actorId,
                    'actor_user_id' => $actorId,
                    'action'        => 'account_links.backfill_merge_2026_09_25',
                    'target_type'   => 'account_links',
                    'target_id'     => null,
                    'ip'            => null,
                    'user_agent'    => 'console: users:backfill-merged-account-links',
                    'meta'          => json_encode([
                        'rows'      => self::ROWS,
                        'inserted'  => $inserted,
                        'source'    => 'report/account_links_audit_2026-09-25.md',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        });

        $this->info("Вставлено: {$inserted}");

        return self::SUCCESS;
    }
}
