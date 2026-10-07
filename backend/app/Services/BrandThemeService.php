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
    private const PRIMARY_GLOW = '#237EF6';

    public const BASE = [
        'day' => [
            'primary'   => '#2967BA',
            'secondary' => '#E7612F',
            'bg_page'   => '#C8DCFF',
            'bg_page_to' => '#C8DCFF', // конец градиента фона; совпадает с bg_page => сплошной фон
            'orb_main'   => '#2967BA', // два больших размытых шара фона (десктоп)
            'orb_center' => '#FFFFFF', // центральный пульсирующий шар
            'bg_card'   => '#FFFFFF',
            'text'      => '#2C2C2C',
            'menu_bg'     => '#FFFFFF',
            'menu_text'   => '#333333',
            'menu_title'  => '#2967BA',
            'menu_accent' => '#2967BA',
            'menu_icon'       => '#E7612F',
            'menu_icon_hover' => '#2967BA',
        ],
        'night' => [
            'primary'   => '#2967BA',
            'secondary' => '#E7612F',
            'bg_page'   => '#161721',
            'bg_page_to' => '#161721',
            'orb_main'   => '#E7612F',
            'orb_center' => '#000000',
            'bg_card'   => '#222333',
            'text'      => '#CACACA',
            'menu_bg'     => '#222333',
            'menu_text'   => '#D0D0E0',
            'menu_title'  => '#528CD9',
            'menu_accent' => '#2967BA',
            'menu_icon'       => '#E7612F',
            'menu_icon_hover' => '#FFB171',
        ],
    ];

    /** Светлый парный цвет градиента вторичного акцента (#FFB171 у оранжевого). */
    private const SECONDARY_LIGHT = '#FFB171';

    public static function keys(): array
    {
        return array_keys(self::BASE['day']);
    }

    public function css(Brand $brand, bool $cache = true): string
    {
        $hidden = $brand->menuHidden();
        if (!$brand->hasTheme() && !$hidden) {
            return '';
        }

        $cssFile = public_path('assets/style.css');
        $key = 'brand.theme.css.' . md5(json_encode([$brand->theme, $hidden, url('/')]) . '|' . @filemtime($cssFile) . '|' . @filemtime(__FILE__));

        $make = fn () => ($brand->hasTheme() ? $this->build((array) $brand->theme, $cssFile) . $this->tokenCss((array) $brand->theme) . $this->fontCss((array) $brand->theme) : '') . $this->hiddenMenuCss($hidden);

        // Предпросмотр несохранённой темы — без записи в кэш (иначе каждое нажатие клавиши оседало бы навсегда)
        return $cache ? Cache::rememberForever($key, $make) : $make();
    }

    /** Подпись временной ссылки предпросмотра: бренд + срок (без сессии — DetectBrand работает до StartSession). */
    public static function previewSig(int $brandId, int $exp): string
    {
        return hash_hmac('sha256', "brand-preview|$brandId|$exp", (string) config('app.key'));
    }

    /** Ключи токенов (config/brand_tokens.php) => [ [день, ночь] по умолчанию ]. */
    public static function tokens(): array
    {
        $out = [];
        foreach ((array) config('brand_tokens.groups') as $group) {
            foreach ($group['tokens'] as $key => [$label, $defaults]) {
                $out['t_' . $key] = $defaults;
            }
        }

        return $out;
    }

    /**
     * Токены элементов: одно правило на токен, выводятся ПОСЛЕ общей темы, !important (специфичность style.css местами выше).
     */
    private function tokenCss(array $theme): string
    {
        $out = '';
        foreach ((array) config('brand_tokens.groups') as $group) {
            foreach ($group['tokens'] as $key => [$label, $defaults, $rules]) {
                foreach (['day' => 'body', 'night' => 'body.dark'] as $mode => $prefix) {
                    $val = $theme[$mode]['t_' . $key] ?? null;
                    if (!$this->validHex($val)) {
                        continue;
                    }
                    foreach ($rules as [$sel, $prop]) {
                        $out .= str_replace('{M}', $prefix, $sel) . '{'
                            . str_replace(';', '!important;', $prop) . ':' . $val . '!important}';
                    }
                }
            }
        }

        return $out;
    }

    /** @font-face + font-family для выбранного шрифта бренда (theme.font = ключ из config/brand_tokens.php → fonts). */
    private function fontCss(array $theme): string
    {
        $font = config('brand_tokens.fonts.' . ($theme['font'] ?? ''));
        if (!$font) {
            return '';
        }
        $out = '';
        foreach ($font['files'] as $weight => $file) {
            $out .= "@font-face{font-family:'{$font['family']}';src:url('{$file}') format('opentype');font-weight:{$weight};font-style:normal;font-display:swap}";
        }

        return $out . "body,button,input,select,textarea,.btn{font-family:'{$font['family']}','Open Sans',sans-serif}";
    }

    /** Скрытие пунктов меню по href (относительный и абсолютный вид — route() отдаёт абсолютный). Только пункты из каталога. */
    private function hiddenMenuCss(array $paths): string
    {
        $known = [];
        foreach ((array) config('brand_menu.groups') as $group) {
            foreach ($group['items'] as [$path]) {
                $known[$path] = true;
            }
        }
        $sel = [];
        foreach ($paths as $path) {
            if (isset($known[$path])) {
                $sel[] = 'a.menu-item[href="' . $path . '"]';
                $sel[] = 'a.menu-item[href="' . url($path) . '"]';
            }
        }

        return $sel ? implode(',', $sel) . '{display:none!important}' : '';
    }

    private function build(array $theme, string $cssFile): string
    {
        $maps = [
            'day'   => $this->colorMap('day', (array) ($theme['day'] ?? [])),
            'night' => $this->colorMap('night', (array) ($theme['night'] ?? [])),
        ];

        $rgb = [
            'day'   => $this->rgbMap('day', (array) ($theme['day'] ?? [])),
            'night' => $this->rgbMap('night', (array) ($theme['night'] ?? [])),
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

            $emit = function (array $sels, string $mode, ?string $vsMode) use ($rule, &$out, $maps, $rgb) {
                $map = $maps[$mode];
                if (!$sels || (!$map && !$rgb[$mode])) {
                    return;
                }
                $decls = [];
                $parts = $this->splitTopLevel($rule['body'], ';');
                foreach ($parts as $i => $decl) {
                    // шорткат `background:` с градиентом в оригинале перекрыт последующим `background-image` (иконка) —
                    // он мёртв, а переизлучив его, мы бы стёрли иконку (.alert-volleyball::before)
                    if (preg_match('/^\s*background\s*:(?!\s*#[0-9a-f]{3,8}\s*(!important)?\s*$)/i', $decl)
                        && preg_grep('/^\s*background-image\s*:/i', array_slice($parts, $i + 1))) {
                        continue;
                    }
                    $replaced = $this->applyMap($decl, $map, $rgb[$mode]);
                    if ($replaced === $decl) {
                        continue;
                    }
                    // ночной override для дневного правила нужен, только если цвет отличается от дневного
                    if ($vsMode !== null && $this->applyMap($decl, $maps[$vsMode], $rgb[$vsMode]) === $replaced) {
                        continue;
                    }
                    // `background: #hex` — шорткат, он обнулил бы background-image (иконки .alert-*::before).
                    // Оригинальное правило со сбросом остаётся в style.css, поэтому переизлучаем только цвет.
                    $replaced = preg_replace('/^(\s*)background\s*:\s*(#[0-9a-f]{3,8})\s*(!important)?\s*$/i', '$1background-color:$2$3', $replaced);
                    $decls[] = trim($replaced);
                }
                if (!$decls) {
                    return;
                }
                $block = implode(',', $sels) . '{' . implode(';', $decls) . '}';
                $out .= $rule['at'] ? $rule['at'] . '{' . $block . '}' : $block;
            };

            $emit($light, 'day', null);
            $emit($dark, 'night', null);
            $emit($lightNight, 'night', 'day');
        }

        return $out . $this->surfaceCss($theme);
    }

    /** Подстановка hex-карты и rgba()-вариантов базовых акцентов (rgba(41, 103, 186, .1) и т.п.). */
    private function applyMap(string $decl, array $map, array $rgb): string
    {
        $out = $map ? strtr($decl, $map) : $decl;
        if (!$rgb) {
            return $out;
        }

        return preg_replace_callback(
            '/rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,/i',
            fn ($m) => isset($rgb["$m[1],$m[2],$m[3]"]) ? 'rgba(' . $rgb["$m[1],$m[2],$m[3]"] . ',' : $m[0],
            $out
        );
    }

    /** "r,g,b" базового акцента => "r,g,b" акцента бренда (только primary/secondary). */
    private function rgbMap(string $mode, array $vals): array
    {
        $map = [];
        foreach (['primary', 'secondary'] as $k) {
            $to = $vals[$k] ?? null;
            if ($to && $this->validHex($to) && strcasecmp(self::BASE[$mode][$k], $to) !== 0) {
                $map[implode(',', $this->rgb(self::BASE[$mode][$k]))] = implode(',', $this->rgb($to));
            }
        }

        return $map;
    }

    /**
     * Карта замены "старый hex => новый hex" (регистронезависимо: ключи в нижнем и верхнем регистре).
     */
    private function colorMap(string $mode, array $vals): array
    {
        $base = self::BASE[$mode];
        $map = [];
        // $enc: акценты бывают зашиты в SVG data-URI как %23RRGGBB (иконка перетаскивания в таблицах) —
        // подменяем и такой вид. Только для primary/secondary: у остальных цветов такие иконки
        // (стрелка select) держат контраст с фоном полей, который от темы не зависит.
        $add = function (string $from, ?string $to, bool $enc = false) use (&$map) {
            if ($to && $this->validHex($to) && strcasecmp($from, $to) !== 0) {
                $map[strtolower($from)] = $to;
                $map[strtoupper($from)] = $to;
                if ($enc) {
                    $map['%23' . strtolower(ltrim($from, '#'))] = '%23' . ltrim($to, '#');
                    $map['%23' . strtoupper(ltrim($from, '#'))] = '%23' . ltrim($to, '#');
                }
            }
        };

        $add($base['primary'], $vals['primary'] ?? null, true);
        $add($base['secondary'], $vals['secondary'] ?? null, true);
        // #237ef6 — более яркий синий подсветки рамок (.ramka::after при наведении)
        if (!empty($vals['primary']) && $this->validHex($vals['primary'])) {
            $add(self::PRIMARY_GLOW, $this->mix($vals['primary'], '#FFFFFF', 0.15));
        }
        if (!empty($vals['secondary']) && $this->validHex($vals['secondary'])) {
            $add(self::SECONDARY_LIGHT, $this->mix($vals['secondary'], '#FFFFFF', 0.4));
        }
        // Цвет текста алертов (info/warning/danger, пилюли score-pill) выводится из акцентов:
        // иначе при несиневом акценте фон и иконка меняются, а текст остаётся синим/красным.
        $pri = $this->validHex($vals['primary'] ?? '') ? $vals['primary'] : null;
        $sec = $this->validHex($vals['secondary'] ?? '') ? $vals['secondary'] : null;
        $night = $mode === 'night';
        if ($pri) {
            $add($night ? '#A5C4EB' : '#1A4A8A', $night ? $this->mix($pri, '#FFFFFF', 0.6) : $this->mix($pri, '#000000', 0.36));
        }
        if ($sec) {
            $add($night ? '#FFAAA3' : '#A8231A', $night ? $this->mix($sec, '#FFFFFF', 0.55) : $this->mix($sec, '#000000', 0.4));
            $add($night ? '#FFC085' : '#B84D00', $night ? $this->mix($sec, '#FFFFFF', 0.45) : $this->mix($sec, '#000000', 0.2));
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

        $out .= $this->pageBackgroundCss('body', 'day', $day);
        if ($this->validHex($day['text'] ?? '')) {
            $out .= 'body{color:' . $day['text'] . '}';
        }
        if ($this->validHex($day['bg_card'] ?? '')) {
            $c = $day['bg_card'];
            $out .= '.card{background:' . $c . '}'
                . '.ramka,.card-ramka{background-image:linear-gradient(to bottom,'
                . $this->rgba($c, 0.9) . ' 0%,' . $this->rgba($c, 0.7) . ' 100%)}';
        }

        $out .= $this->pageBackgroundCss('body.dark', 'night', $night);
        if ($this->validHex($night['text'] ?? '')) {
            $out .= 'body.dark{color:' . $night['text'] . '}';
        }
        if ($this->validHex($night['bg_card'] ?? '')) {
            $c = $night['bg_card'];
            $out .= 'body.dark .card{background:' . $c . '}'
                . 'body.dark .ramka,body.dark .card-ramka{background-image:linear-gradient(to bottom,'
                . $this->rgba($c, 0.8) . ' 0%,' . $this->rgba($this->mix($c, '#000000', 0.12), 0.5) . ' 100%)}';
        }

        // Шары фона (.bg-orb): без своей настройки следуют акцентам через общую карту hex
        foreach (['day' => ['', $day], 'night' => ['body.dark ', $night]] as [$pfx, $v]) {
            if ($this->validHex($v['orb_main'] ?? '')) {
                $out .= $pfx . '.orb-1,' . $pfx . '.orb-2{background:' . $v['orb_main'] . '}';
            }
            if ($this->validHex($v['orb_center'] ?? '')) {
                $out .= $pfx . '.orb-3{background:' . $v['orb_center'] . '}';
            }
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
            // Иконки шапки: вход/аватар, почта, гамбургер, тема (+ рамка аватара)
            if ($this->validHex($v['menu_icon'] ?? '')) {
                $c = $v['menu_icon'];
                $out .= $pfx . '.fix-header-btn-user svg,' . $pfx . '.fix-header-btn-hamm svg,' . $pfx . '.fix-header-btn-mail svg,' . $pfx . '.theme-icon{fill:' . $c . '}'
                    . $pfx . '.fix-header-btn-user .user-avatar-small img{border-color:' . $c . '}';
            }
            if ($this->validHex($v['menu_icon_hover'] ?? '')) {
                $c = $v['menu_icon_hover'];
                $out .= '@media (hover:hover) and (pointer:fine){'
                    . $pfx . '.fix-header-btn-user:hover svg,' . $pfx . '.fix-header-btn-hamm:hover svg,' . $pfx . '.fix-header-btn-mail:hover svg,'
                    . $pfx . '.fix-header-users:hover .fix-header-btn-user svg,' . $pfx . '.fix-header-btn-theme:hover .theme-icon{fill:' . $c . '}}';
            }
            if ($this->validHex($v['menu_accent'] ?? '')) {
                $out .= $pfx . '.menu-item::after{background:' . $v['menu_accent'] . '}'
                    . $pfx . '.fix-header-user.active{color:' . $v['menu_accent'] . '!important}';
            }
        }

        return $out;
    }

    /** Фон страницы: сплошной или градиент (bg_page → bg_page_to), если задан конечный цвет. */
    private function pageBackgroundCss(string $sel, string $mode, array $v): string
    {
        $from = $this->validHex($v['bg_page'] ?? '') ? $v['bg_page'] : null;
        $to = $this->validHex($v['bg_page_to'] ?? '') ? $v['bg_page_to'] : null;
        if ($to) {
            $from = $from ?: self::BASE[$mode]['bg_page'];
            if (strcasecmp($from, $to) !== 0) {
                return $sel . '{background:' . $from . ' linear-gradient(160deg,' . $from . ' 0%,' . $to . ' 100%) fixed}';
            }
        }

        return $from ? $sel . '{background:' . $from . '}' : '';
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
