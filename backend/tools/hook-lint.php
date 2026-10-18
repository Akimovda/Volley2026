<?php
// PostToolUse-хук Claude Code: после Edit/Write проверяет .blade.php (компиляция + php -l) и .php (php -l).
// Ошибка → код 2 и текст в stderr (Claude увидит и исправит).
$in = json_decode(stream_get_contents(STDIN), true);
$file = $in['tool_input']['file_path'] ?? '';
if ($file === '' || !is_file($file)) exit(0);

if (str_ends_with($file, '.blade.php')) {
    exec('php ' . escapeshellarg(__DIR__ . '/blade-lint.php') . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0 || preg_grep('/^WARN/', $out)) { fwrite(STDERR, implode("\n", $out) . "\n"); exit(2); }
} elseif (str_ends_with($file, '.php')) {
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0) { fwrite(STDERR, implode("\n", $out) . "\n"); exit(2); }
}
exit(0);
