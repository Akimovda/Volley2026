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
    protected $description = 'Пересчитать OpenSkill (mu/sigma/CR + история/пары/соперники) по всей истории матчей';

    public function handle(TournamentOpenSkillService $service): int
    {
        $this->info('Пересчёт OpenSkill по историческим матчам...');
        $service->rebuildAll();

        $this->newLine();
        $this->info('✅ Топ-15 по Conservative Rating:');
        $this->showCareerTop();

        $this->newLine();
        $this->info('Топ-10 пар (matches_together):');
        $this->showPairTop();

        $this->newLine();
        $this->info('Последние 20 записей в player_rating_history:');
        $this->showHistory();

        return self::SUCCESS;
    }

    private function showCareerTop(): void
    {
        $rows = PlayerCareerStats::where('total_matches', '>', 0)
            ->with('user:id,first_name,last_name')
            ->orderByDesc(DB::raw('mu - 3 * sigma'))
            ->limit(15)
            ->get();

        $headers = ['#', 'Игрок', 'CR', 'μ', 'σ', 'Пик μ', 'WinRate', 'Матчи', 'Прт.', 'Сопер.', 'Стаб.%', 'Ф-5', 'ОЗ/ОП'];
        $data = $rows->map(function ($s, $i) {
            $name = trim(($s->user->last_name ?? '') . ' ' . ($s->user->first_name ?? '')) ?: '#'.$s->user_id;
            return [
                $i + 1,
                $name . ' (' . $s->direction . ')',
                number_format(max(0, $s->mu - 3 * $s->sigma), 2),
                number_format($s->mu, 3),
                number_format($s->sigma, 3),
                number_format($s->mu_peak ?? 25, 3),
                $s->match_win_rate . '%',
                $s->total_matches,
                $s->unique_partners ?? 0,
                $s->unique_opponents ?? 0,
                number_format($s->pair_stability ?? 0, 1),
                $s->last_5_form ?? '—',
                number_format($s->points_ratio ?? 1, 3),
            ];
        });

        $this->table($headers, $data);
    }

    private function showPairTop(): void
    {
        $rows = DB::table('player_pair_stats as ps')
            ->join('users as u1', 'u1.id', '=', 'ps.player1_id')
            ->join('users as u2', 'u2.id', '=', 'ps.player2_id')
            ->orderByDesc('ps.matches_together')
            ->limit(10)
            ->select(
                'ps.player1_id', 'ps.player2_id',
                'ps.matches_together', 'ps.wins_together',
                DB::raw("CONCAT(u1.last_name, ' ', u1.first_name) as name1"),
                DB::raw("CONCAT(u2.last_name, ' ', u2.first_name) as name2")
            )
            ->get();

        $headers = ['Игрок 1', 'Игрок 2', 'Игр вместе', 'Побед', 'WinRate%'];
        $data = $rows->map(fn($r) => [
            trim($r->name1),
            trim($r->name2),
            $r->matches_together,
            $r->wins_together,
            $r->matches_together > 0
                ? number_format($r->wins_together / $r->matches_together * 100, 1) . '%'
                : '—',
        ]);

        $this->table($headers, $data);
    }

    private function showHistory(): void
    {
        $rows = DB::table('player_rating_history as h')
            ->join('users as u', 'u.id', '=', 'h.user_id')
            ->orderByDesc('h.id')
            ->limit(20)
            ->select(
                'h.id', 'h.user_id',
                DB::raw("CONCAT(u.last_name, ' ', u.first_name) as name"),
                'h.mu_before', 'h.mu_after', 'h.mu_delta',
                'h.sigma_before', 'h.sigma_after',
                'h.recorded_at'
            )
            ->get();

        $headers = ['ID', 'Игрок', 'μ до', 'μ после', 'Δμ', 'σ до', 'σ после'];
        $data = $rows->map(fn($r) => [
            $r->id,
            trim($r->name),
            number_format($r->mu_before, 3),
            number_format($r->mu_after, 3),
            ($r->mu_delta >= 0 ? '+' : '') . number_format($r->mu_delta, 3),
            number_format($r->sigma_before, 3),
            number_format($r->sigma_after, 3),
        ]);

        $this->table($headers, $data);
    }
}
