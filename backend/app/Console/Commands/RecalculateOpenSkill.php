<?php

namespace App\Console\Commands;

use App\Models\PlayerCareerStats;
use App\Models\TournamentSeasonStats;
use App\Services\TournamentOpenSkillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateOpenSkill extends Command
{
    protected $signature   = 'tournament:recalculate-openskill';
    protected $description = 'Пересчитать OpenSkill (mu/sigma/CR) по всей истории матчей';

    public function handle(TournamentOpenSkillService $service): int
    {
        $this->info('Сброс mu/sigma до дефолтных значений...');
        PlayerCareerStats::query()->update([
            'mu'    => TournamentOpenSkillService::INITIAL_MU,
            'sigma' => TournamentOpenSkillService::INITIAL_SIGMA,
        ]);
        TournamentSeasonStats::query()->update([
            'mu_season'    => TournamentOpenSkillService::INITIAL_MU,
            'sigma_season' => TournamentOpenSkillService::INITIAL_SIGMA,
        ]);

        $this->info('Пересчёт по историческим матчам...');
        $service->rebuildAll();

        $this->info('');
        $this->info('✅ Готово. Топ-10 по Career Conservative Rating:');
        $this->showCareerTop10();

        $this->info('');
        $this->info('Топ-10 по WinRate (для сравнения):');
        $this->showWinRateTop10();

        return self::SUCCESS;
    }

    private function showCareerTop10(): void
    {
        $rows = PlayerCareerStats::where('total_matches', '>=', 3)
            ->with('user:id,first_name,last_name')
            ->orderByDesc(DB::raw('mu - 3 * sigma'))
            ->limit(10)
            ->get();

        $headers = ['#', 'Игрок', 'CR', 'μ', 'σ', 'WinRate', 'Матчи'];
        $data = $rows->map(function ($s, $i) {
            $name = trim(($s->user->last_name ?? '') . ' ' . ($s->user->first_name ?? '')) ?: '#'.$s->user_id;
            return [
                $i + 1,
                $name . ' (' . $s->direction . ')',
                number_format(max(0, $s->mu - 3 * $s->sigma), 2),
                number_format($s->mu, 3),
                number_format($s->sigma, 3),
                $s->match_win_rate . '%',
                $s->total_matches,
            ];
        });

        $this->table($headers, $data);
    }

    private function showWinRateTop10(): void
    {
        $rows = PlayerCareerStats::where('total_matches', '>=', 3)
            ->with('user:id,first_name,last_name')
            ->orderByDesc('match_win_rate')
            ->limit(10)
            ->get();

        $headers = ['#', 'Игрок', 'WinRate', 'CR', 'Матчи'];
        $data = $rows->map(function ($s, $i) {
            $name = trim(($s->user->last_name ?? '') . ' ' . ($s->user->first_name ?? '')) ?: '#'.$s->user_id;
            return [
                $i + 1,
                $name . ' (' . $s->direction . ')',
                $s->match_win_rate . '%',
                number_format(max(0, $s->mu - 3 * $s->sigma), 2),
                $s->total_matches,
            ];
        });

        $this->table($headers, $data);
    }
}
