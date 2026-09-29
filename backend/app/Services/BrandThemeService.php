<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Brand;
use Illuminate\Support\Facades\Cache;

/**
 * Генерирует override-CSS для white-label брендов.
 *
 * style.css не содержит CSS-переменных — цвета зашиты жёстко. Вместо ручного
 * списка селекторов сервис разбирает style.css, находит все правила с
 * базовыми цветами (синий/оранжевый акцент, фон, текст) и переизлучает
 * ТОЛЬКО эти декларации с цветами бренда. Основной style.css не меняется.
 */
class BrandThemeService
{
    /** Базовые цвета style.css (ключ настройки => hex по умолчанию). */
    public const BASE = [
        'day' => [
            'primary'   => '#2967BA',
            'secondary' => '#E7612F',
            'bg_page'   => '#C8DCFF',
            'bg_card'   => '#FFFFFF',
            'text'      => '#2C2C2C',
            'menu_bg'     => '#FFFFFF',
            'menu_text'   => '#333333',
            'menu_title'  => '#2967BA',
            'menu_accent' => '#2967BA',
        ],
        'night' => [
            'primary'   => '#2967BA',
            'secondary' => '#E7612F',
            'bg_page'   => '#161721',
            'bg_card'   => '#222333',
            'text'      => '#CACACA',
            'menu_bg'     => '#222333',
            'menu_text'   => '#D0D0E0',
            'menu_title'  => '#528CD9',
            'menu_accent' => '#2967BA',
        ],
    ];

    /** Светлый парный цвет градиента вторичного акцента (#FFB171 у оранжевого). */
    private const SECONDARY_LIGHT = '#FFB171';

    public static function keys(): array
    {
        return array_keys(self::BASE['day']);
    }

    public function css(Brand $brand): string
    {
        if (!$brand->hasTheme()) {
            return '';
        }

        $cssFile = public_path('assets/style.css');
        $key = 'brand.theme.css.' . md5(json_encode($brand->theme) . '|' . @filemtime($cssFile));

        return Cache::rememberForever($key, fn () => $this->build((array) $brand->theme, $cssFile));
    }

    private function build(array $theme, string $cssFile): string
    {
        $maps = [
            'day'   => $this->colorMap('day', (array) ($theme['day'] ?? [])),
            'night' => $this->colorMap('night', (array) ($theme['night'] ?? [])),
        ];

        $out = '';
        foreach ($this->parseRules((string) @file_get_contents($cssFile)) as $rule) {
            $selectors = array_filter(array_map('trim', $this->splitTopLevel($rule['selector'], ',')), fn ($s) => $s !== '');
            $dark = array_filter($selectors, fn ($s) => str_contains($s, 'body.dark'));
            $light = array_diff($selectors, $dark);
            // Дневное правило без body.dark действует и ночью — ночной цвет
            // выдаём отдельным правилом с префиксом body.dark (выше специфичность).
            $lightNight = array_filter(
                array_map(fn ($s) => preg_match('/^(html|body|:root|\*)/i', $s) ? null : 'body.dark ' . $s, $light)
            );

            $emit = function (array $sels, array $map, ?array $vsMap) use ($rule, &$out) {
                if (!$sels || !$map) {
                    return;
                }
                $decls = [];
                foreach ($this->splitTopLevel($rule['body'], ';') as $decl) {
                    $replaced = strtr($decl, $map);
                    if ($replaced === $decl) {
                        continue;
                    }
                    // ночной override для дневного правила нужен, только если цвет отличается от дневного
                    if ($vsMap !== null && strtr($decl, $vsMap) === $replaced) {
                        continue;
                    }
                    $decls[] = trim($replaced);
                }
                if (!$decls) {
                    return;
                }
                $block = implode(',', $sels) . '{' . implode(';', $decls) . '}';
                $out .= $rule['at'] ? $rule['at'] . '{' . $block . '}' : $block;
            };

            $emit($light, $maps['day'], null);
            $emit($dark, $maps['night'], null);
            $emit($lightNight, $maps['night'], $maps['day']);
        }

        return $out . $this->surfaceCss($theme);
    }

    /**
     * Карта замены "старый hex => новый hex" (регистронезависимо: ключи в нижнем и верхнем регистре).
     */
    private function colorMap(string $mode, array $vals): array
    {
        $base = self::BASE[$mode];
        $map = [];
        $add = function (string $from, ?string $to) use (&$map) {
            if ($to && $this->validHex($to) && strcasecmp($from, $to) !== 0) {
                $map[strtolower($from)] = $to;
                $map[strtoupper($from)] = $to;
            }
        };

        $add($base['primary'], $vals['primary'] ?? null);
        $add($base['secondary'], $vals['secondary'] ?? null);
        if (!empty($vals['secondary']) && $this->validHex($vals['secondary'])) {
            $add(self::SECONDARY_LIGHT, $this->mix($vals['secondary'], '#FFFFFF', 0.4));
        }
        $add($base['text'], $vals['text'] ?? null);
        if ($mode === 'night') {
            $add($base['bg_page'], $vals['bg_page'] ?? null);
            $add($base['bg_card'], $vals['bg_card'] ?? null);
        } else {
            $add($base['bg_page'], $vals['bg_page'] ?? null);
        }

        return $map;
    }

    /** Фон body/карточек/рамок, заданный не hex-ом (градиенты .ramka) или не через базовый цвет (.card #fff). */
    private function surfaceCss(array $theme): string
    {
        $out = '';
        $day = (array) ($theme['day'] ?? []);
        $night = (array) ($theme['night'] ?? []);

        if ($this->validHex($day['bg_page'] ?? '')) {
            $out .= 'body{background:' . $day['bg_page'] . '}';
        }
        if ($this->validHex($day['text'] ?? '')) {
            $out .= 'body{color:' . $day['text'] . '}';
        }
        if ($this->validHex($day['bg_card'] ?? '')) {
            $c = $day['bg_card'];
            $out .= '.card{background:' . $c . '}'
                . '.ramka,.card-ramka{background-image:linear-gradient(to bottom,'
                . $this->rgba($c, 0.9) . ' 0%,' . $this->rgba($c, 0.7) . ' 100%)}';
        }

        if ($this->validHex($night['bg_page'] ?? '')) {
            $out .= 'body.dark{background:' . $night['bg_page'] . '}';
        }
        if ($this->validHex($night['text'] ?? '')) {
            $out .= 'body.dark{color:' . $night['text'] . '}';
        }
        if ($this->validHex($night['bg_card'] ?? '')) {
            $c = $night['bg_card'];
            $out .= 'body.dark .card{background:' . $c . '}'
                . 'body.dark .ramka,body.dark .card-ramka{background-image:linear-gradient(to bottom,'
                . $this->rgba($c, 0.8) . ' 0%,' . $this->rgba($this->mix($c, '#000000', 0.12), 0.5) . ' 100%)}';
        }

        // Меню и шапка (.fix-header содержит и выпадающее меню, и кнопку пользователя)
        foreach (['day' => ['', $day], 'night' => ['body.dark ', $night]] as [$pfx, $v]) {
            if ($this->validHex($v['menu_bg'] ?? '')) {
                $c = $v['menu_bg'];
                [$a1, $a2, $c2] = $pfx ? [0.8, 0.5, $this->mix($c, '#000000', 0.12)] : [0.9, 0.7, $c];
                $out .= $pfx . '.fix-header{background-image:linear-gradient(to bottom,'
                    . $this->rgba($c, $a1) . ' 0%,' . $this->rgba($c2, $a2) . ' 100%)}';
            }
            if ($this->validHex($v['menu_text'] ?? '')) {
                $out .= $pfx . '.menu-item,' . $pfx . '.fix-header-user{color:' . $v['menu_text'] . '}';
            }
            if ($this->validHex($v['menu_title'] ?? '')) {
                $out .= $pfx . '.menu-item-title:not(.admin){color:' . $v['menu_title'] . '}';
            }
            if ($this->validHex($v['menu_accent'] ?? '')) {
                $out .= $pfx . '.menu-item::after{background:' . $v['menu_accent'] . '}'
                    . $pfx . '.fix-header-user.active{color:' . $v['menu_accent'] . '!important}';
            }
        }

        return $out;
    }

    public function validHex(?string $hex): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $hex);
    }

    private function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private function rgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = $this->rgb($hex);

        return "rgba($r,$g,$b,$alpha)";
    }

    private function mix(string $a, string $b, float $ratio): string
    {
        $ca = $this->rgb($a);
        $cb = $this->rgb($b);

        return sprintf(
            '#%02X%02X%02X',
            (int) round($ca[0] + ($cb[0] - $ca[0]) * $ratio),
            (int) round($ca[1] + ($cb[1] - $ca[1]) * $ratio),
            (int) round($ca[2] + ($cb[2] - $ca[2]) * $ratio),
        );
    }

    /**
     * Разбор CSS в плоский список правил [at => обёртка @media|@supports|'', selector, body].
     * @keyframes/@font-face и прочие at-правила без селекторов пропускаются.
     */
    private function parseRules(string $css): array
    {
        $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? '';
        $rules = [];
        $len = strlen($css);
        $i = 0;
        $this->parseBlock($css, $i, $len, '', $rules);

        return $rules;
    }

    private function parseBlock(string $css, int &$i, int $len, string $at, array &$rules): void
    {
        while ($i < $len) {
            $open = strpos($css, '{', $i);
            $close = strpos($css, '}', $i);
            if ($close !== false && ($open === false || $close < $open)) {
                $i = $close + 1; // конец текущего блока

                return;
            }
            if ($open === false) {
                $i = $len;

                return;
            }
            $prelude = trim(substr($css, $i, $open - $i));
            $i = $open + 1;

            if (str_starts_with($prelude, '@')) {
                if (preg_match('/^@(media|supports)\b/i', $prelude)) {
                    $inner = [];
                    $this->parseBlock($css, $i, $len, $prelude, $inner);
                    foreach ($inner as $r) {
                        $rules[] = $r;
                    }
                } else {
                    $this->skipBlock($css, $i, $len);
                }
                continue;
            }

            $end = strpos($css, '}', $i);
            if ($end === false) {
                $i = $len;

                return;
            }
            $rules[] = ['at' => $at, 'selector' => $prelude, 'body' => substr($css, $i, $end - $i)];
            $i = $end + 1;
        }
    }

    private function skipBlock(string $css, int &$i, int $len): void
    {
        $depth = 1;
        while ($i < $len && $depth > 0) {
            $c = $css[$i++];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
            }
        }
    }

    /** Разделение по символу вне скобок (запятая в rgba()/:not(), ; в url(data:...)). */
    private function splitTopLevel(string $s, string $sep): array
    {
        $parts = [];
        $depth = 0;
        $buf = '';
        for ($k = 0, $n = strlen($s); $k < $n; $k++) {
            $c = $s[$k];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth = max(0, $depth - 1);
            }
            if ($c === $sep && $depth === 0) {
                $parts[] = $buf;
                $buf = '';
            } else {
                $buf .= $c;
            }
        }
        if (trim($buf) !== '') {
            $parts[] = $buf;
        }

        return $parts;
    }
}
