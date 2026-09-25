<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;

class RoutesCheckUsage extends Command
{
    protected $signature = 'routes:check-usage
        {--paths=app,resources/views,routes : Comma-separated dirs (relative to base_path) to scan}';

    protected $description = 'Read-only: найти route(\'name\')/->route(\'name\') с именами, которых нет в route:list (защита от регрессий вида api.users.search)';

    /** @var string[] */
    private array $definedNames = [];

    public function handle(): int
    {
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name !== null) {
                $this->definedNames[$name] = true;
            }
        }

        $paths = array_map('trim', explode(',', (string) $this->option('paths')));
        // Исключаем $request->route('param')/request()->route('param') — это Request::route()
        // (аксессор route-параметра из URL), а не хелпер генерации URL по имени роута.
        $pattern = '/(?<!request\(\)->)(?<!\$request->)(?<![A-Za-z0-9_])(?:route|to_route)\(\s*[\'"]([A-Za-z0-9_.\-]+)[\'"]/';

        $missing = []; // name => [ "file:line", ... ]
        $dynamicHits = 0;

        foreach ($paths as $rel) {
            $dir = base_path($rel);
            if (!is_dir($dir)) {
                continue;
            }

            $finder = (new Finder())->files()->in($dir)->name(['*.php', '*.blade.php']);

            foreach ($finder as $file) {
                $lines = file($file->getRealPath());
                foreach ($lines as $i => $line) {
                    if (!preg_match_all($pattern, $line, $matches, PREG_SET_ORDER)) {
                        continue;
                    }
                    foreach ($matches as $m) {
                        $name = $m[1];
                        if (!isset($this->definedNames[$name])) {
                            $missing[$name][] = $file->getRelativePathname() . ':' . ($i + 1);
                        }
                    }
                }
                // Грубая оценка динамических имён route($var) — не детектируются regex'ом выше,
                // считаем отдельно по вхождениям "route($" без кавычки сразу после.
                $dynamicHits += preg_match_all('/(?<![A-Za-z0-9_])(?:route|to_route)\(\s*\$/', implode('', $lines));
            }
        }

        if (empty($missing)) {
            $this->info('Не найдено ссылок на несуществующие именованные роуты.');
        } else {
            $this->error('Найдены ссылки на несуществующие именованные роуты:');
            foreach ($missing as $name => $locations) {
                $this->line("  <fg=red>{$name}</> — не в route:list");
                foreach ($locations as $loc) {
                    $this->line("      {$loc}");
                }
            }
        }

        if ($dynamicHits > 0) {
            $this->comment("Пропущено ~{$dynamicHits} вызовов route(\$var) с динамическим именем — не проверяются этим скриптом.");
        }

        return empty($missing) ? self::SUCCESS : self::FAILURE;
    }
}
