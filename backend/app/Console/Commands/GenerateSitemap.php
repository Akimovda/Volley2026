<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\Location;
use App\Models\User;
use App\Models\TournamentSeason;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Generate sitemap.xml for SEO';

    public function handle(): int
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $now = now()->toW3cString();

        $urls = [];

        // Статические страницы
        $static = [
            ['/', 'daily', '1.0'],
            ['/events', 'hourly', '0.9'],
            ['/locations', 'weekly', '0.8'],
            ['/users', 'daily', '0.6'],
            ['/about', 'monthly', '0.5'],
            ['/help', 'monthly', '0.5'],
            ['/rules', 'monthly', '0.4'],
            ['/level_players', 'monthly', '0.4'],
            ['/tournament-formats', 'monthly', '0.5'],
        ];

        foreach ($static as [$path, $freq, $priority]) {
            $urls[] = [
                'loc'        => $baseUrl . $path,
                'changefreq' => $freq,
                'priority'   => $priority,
            ];
        }

        // События (публичные, не рекламные)
        $events = Event::where('visibility', 'public')
            ->where(function ($q) {
                $q->whereNull('ad_payment_status')
                  ->orWhere('ad_payment_status', 'paid');
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'updated_at']);

        foreach ($events as $event) {
            $urls[] = [
                'loc'        => $baseUrl . '/events/' . $event->id,
                'lastmod'    => $event->updated_at?->toW3cString() ?? $now,
                'changefreq' => 'daily',
                'priority'   => '0.7',
            ];
        }

        // Локации
        $locations = Location::orderByDesc('id')
            ->limit(500)
            ->get(['id', 'name', 'updated_at']);

        foreach ($locations as $loc) {
            $slug = \Illuminate\Support\Str::slug($loc->name);
            $urls[] = [
                'loc'        => $baseUrl . '/locations/' . $loc->id . '-' . $slug,
                'lastmod'    => $loc->updated_at?->toW3cString() ?? $now,
                'changefreq' => 'weekly',
                'priority'   => '0.6',
            ];
        }

        // Пользователи (только не боты)
        $users = User::where('is_bot', false)
            ->orderByDesc('id')
            ->limit(1000)
            ->get(['id', 'updated_at']);

        foreach ($users as $user) {
            $urls[] = [
                'loc'        => $baseUrl . '/user/' . $user->id,
                'lastmod'    => $user->updated_at?->toW3cString() ?? $now,
                'changefreq' => 'weekly',
                'priority'   => '0.4',
            ];
        }

        // Сезоны
        if (class_exists(TournamentSeason::class)) {
            try {
                $seasons = TournamentSeason::whereNotNull('slug')
                    ->where('status', 'active')
                    ->get(['slug', 'updated_at']);

                foreach ($seasons as $season) {
                    $urls[] = [
                        'loc'        => $baseUrl . '/s/' . $season->slug,
                        'lastmod'    => $season->updated_at?->toW3cString() ?? $now,
                        'changefreq' => 'daily',
                        'priority'   => '0.7',
                    ];
                }
            } catch (\Exception $e) {
                $this->warn('Skipping seasons: ' . $e->getMessage());
            }
        }

        // Генерация XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($url['loc']) . "</loc>\n";
            if (!empty($url['lastmod'])) {
                $xml .= "    <lastmod>" . $url['lastmod'] . "</lastmod>\n";
            }
            $xml .= "    <changefreq>" . $url['changefreq'] . "</changefreq>\n";
            $xml .= "    <priority>" . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        $path = public_path('sitemap.xml');
        file_put_contents($path, $xml);

        $count = count($urls);
        $this->info("Sitemap generated: {$count} URLs → {$path}");

        return self::SUCCESS;
    }
}
