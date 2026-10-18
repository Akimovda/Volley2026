<?php
// Проверка Blade-шаблона: компиляция + php -l результата.
// `view:cache` и `php -l` на самом .blade.php эту ошибку не ловят (см. CLAUDE.md, @json/@include).
// Запуск: php tools/blade-lint.php path/to/file.blade.php [...]
putenv('SENTRY_LARAVEL_DSN=');
$_ENV['SENTRY_LARAVEL_DSN'] = '';
putenv('VIEW_COMPILED_PATH=/tmp/vc');
$_ENV['VIEW_COMPILED_PATH'] = '/tmp/vc';
@mkdir('/tmp/vc', 0775, true);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$bad = 0;
foreach (array_slice($argv, 1) as $file) {
    if (!is_file($file) || !str_ends_with($file, '.blade.php')) continue;
    try {
        $php = app('blade.compiler')->compileString(file_get_contents($file));
    } catch (Throwable $e) {
        echo "BLADE FAIL $file: {$e->getMessage()}\n"; $bad++; continue;
    }
    $tmp = tempnam('/tmp', 'bl') . '.php';
    file_put_contents($tmp, $php);
    exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    unlink($tmp);
    if ($code !== 0) { echo "PHP SYNTAX FAIL $file:\n" . implode("\n", $out) . "\n"; $bad++; }
    $out = [];
    // @include/@else без пробела печатается буквально в HTML и валидный PHP-текст
    if (preg_match('/@else@|@endif@|@include\(.*\)@/', file_get_contents($file))) {
        echo "WARN $file: директивы слиплись без пробела (@else@include и т.п.)\n";
    }
}
if (!$bad) echo "blade-lint ok\n";
exit($bad ? 2 : 0);
