<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Метрики сервера → Uptime Kuma (push-мониторы): диск, память, нагрузка CPU, очередь задач, архив WAL PostgreSQL.
 * Каждые 5 минут шлёт в Kuma статус up/down и значение (ping — число для графика). Превышение порога — status=down,
 * Kuma присылает оповещение в Telegram; если сервер умер и метрики перестали приходить — монитор тоже упадёт сам.
 * Адреса push — в .env: KUMA_PUSH_DISK, KUMA_PUSH_MEMORY, KUMA_PUSH_LOAD, KUMA_PUSH_QUEUE_BACKLOG, KUMA_PUSH_WAL (пусто — метрика не шлётся).
 *
 * Пороги (down): диск занят ≥ 80%; доступной памяти < 15%; load average (5 мин) на ядро > 1.0;
 * в очереди > 500 готовых задач или старейшая ждёт > 10 минут; архив WAL: последняя выгрузка > 30 минут назад или текущая ошибка.
 */
class ReportServerMetrics extends Command
{
    protected $signature = 'ops:report-metrics {--dry-run : Print metrics instead of sending}';

    protected $description = 'Send disk/memory/load/queue metrics to Uptime Kuma push monitors.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        foreach ($this->metrics() as $key => $m) {
            $url = (string) config('services.kuma.' . $key);
            $line = sprintf('%-14s %s  ping=%s  %s', $key, $m['ok'] ? 'up  ' : 'DOWN', $m['ping'], $m['msg']);

            if ($dry) {
                $this->line($line . ($url === '' ? '  [адрес Kuma не задан]' : ''));
                continue;
            }
            if ($url === '') {
                continue;
            }

            try {
                Http::timeout(10)->get($url, [
                    'status' => $m['ok'] ? 'up' : 'down',
                    'msg'    => mb_substr($m['msg'], 0, 200),
                    'ping'   => $m['ping'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('ops:report-metrics: Kuma недоступна', ['metric' => $key, 'error' => $e->getMessage()]);
            }
        }

        return self::SUCCESS;
    }

    /** @return array<string, array{ok: bool, ping: int|float, msg: string}> */
    private function metrics(): array
    {
        $out = [];

        // ── Диск ──
        $total = (float) @disk_total_space('/');
        $free  = (float) @disk_free_space('/');
        $usedPct = $total > 0 ? (int) round(($total - $free) / $total * 100) : 0;
        $out['disk'] = [
            'ok'   => $usedPct < 80,
            'ping' => $usedPct,
            'msg'  => sprintf('Диск: занято %d%%, свободно %.1f ГБ из %.0f', $usedPct, $free / 1024 ** 3, $total / 1024 ** 3),
        ];

        // ── Память (доступная = free + кэш, которое можно отдать) ──
        $mem = $this->meminfo();
        $availPct = $mem['total'] > 0 ? (int) round($mem['available'] / $mem['total'] * 100) : 100;
        $out['memory'] = [
            'ok'   => $availPct >= 15,
            'ping' => 100 - $availPct,
            'msg'  => sprintf('Память: доступно %d%% (%.1f ГБ из %.1f), swap занят %.0f МБ', $availPct, $mem['available'] / 1024 ** 3, $mem['total'] / 1024 ** 3, $mem['swap_used'] / 1024 ** 2),
        ];

        // ── Нагрузка CPU ──
        $cores = max(1, $this->cpuCores());
        $load  = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
        $perCore = round($load[1] / $cores, 2);
        $out['load'] = [
            'ok'   => $perCore <= 1.0,
            'ping' => (int) round($perCore * 100),
            'msg'  => sprintf('Нагрузка: load5 %.2f на %d ядер (%d%% на ядро)', $load[1], $cores, round($perCore * 100)),
        ];

        // ── Очередь задач ──
        try {
            $ready  = (int) DB::table('jobs')->where('available_at', '<=', time())->count();
            $oldest = DB::table('jobs')->where('available_at', '<=', time())->min('available_at');
            $age    = $oldest ? max(0, time() - (int) $oldest) : 0;
            $failed = (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
            $out['queue_backlog'] = [
                'ok'   => $ready <= 500 && $age <= 600,
                'ping' => $ready,
                'msg'  => sprintf('Очередь: готово к выполнению %d, самая старая ждёт %d с, упавших за час %d', $ready, $age, $failed),
            ];
        } catch (\Throwable $e) {
            $out['queue_backlog'] = ['ok' => false, 'ping' => 0, 'msg' => 'Очередь: не удалось прочитать таблицу jobs'];
        }

        // ── Архив WAL PostgreSQL (резервное копирование «на момент времени») ──
        try {
            $mode = (string) (DB::selectOne('show archive_mode')->archive_mode ?? 'off');
            if ($mode === 'off') {
                $out['wal'] = ['ok' => true, 'ping' => 0, 'msg' => 'Архив WAL: ещё не включён'];
            } else {
                $a = DB::selectOne("select archived_count, failed_count,
                        extract(epoch from (now() - last_archived_time))::int as since_arch,
                        (last_failed_time is not null and (last_archived_time is null or last_failed_time > last_archived_time)) as failing
                    from pg_stat_archiver");
                $min = $a->since_arch === null ? 9999 : intdiv((int) $a->since_arch, 60);
                $out['wal'] = [
                    // сегмент выгружается минимум раз в 5 минут (archive_timeout): 30 минут тишины или текущая ошибка — тревога
                    'ok'   => !$a->failing && $min <= 30,
                    'ping' => min($min, 9999),
                    'msg'  => sprintf('Архив WAL: последний сегмент %d мин назад, выгружено %d, ошибок %d%s', $min, $a->archived_count, $a->failed_count, $a->failing ? ' — СЕЙЧАС ОШИБКА' : ''),
                ];
            }
        } catch (\Throwable $e) {
            $out['wal'] = ['ok' => false, 'ping' => 0, 'msg' => 'Архив WAL: не удалось прочитать pg_stat_archiver'];
        }

        return $out;
    }

    private function meminfo(): array
    {
        $raw = @file_get_contents('/proc/meminfo') ?: '';
        $kb = fn (string $k): float => preg_match('/^' . $k . ':\s+(\d+)\s*kB/m', $raw, $m) ? (float) $m[1] * 1024 : 0.0;

        return [
            'total'     => $kb('MemTotal'),
            'available' => $kb('MemAvailable'),
            'swap_used' => max(0.0, $kb('SwapTotal') - $kb('SwapFree')),
        ];
    }

    private function cpuCores(): int
    {
        $raw = @file_get_contents('/proc/cpuinfo') ?: '';

        return (int) preg_match_all('/^processor\s*:/m', $raw);
    }
}
