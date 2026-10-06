<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Оформление виджета организатора: единый источник полей (валидация, дефолты, форма в ЛК)
 * и генератор CSS. CSS самодостаточный (px, без style.css сайта) — одинаково выглядит
 * и в iframe, и в shadow-root на чужом сайте (rem из style.css там зависел бы от html чужого сайта).
 */
class WidgetStyleService
{
    public const FONTS = [
        'system'  => '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif',
        'serif'   => 'Georgia,"Times New Roman",serif',
        'mono'    => 'ui-monospace,SFMono-Regular,Menlo,Consolas,monospace',
        'inherit' => 'inherit',
    ];

    /**
     * Поля оформления по группам: key => [type, default, options]
     * type: color | int (min,max) | enum (values) | bool | text (max)
     */
    public static function groups(): array
    {
        return [
            'general' => [
                'layout'         => ['enum', 'cards', ['cards', 'list', 'wide']],
                'theme'          => ['enum', 'custom', ['custom', 'light', 'dark', 'contrast']],
                'columns_max'    => ['int', 3, [1, 4]],
                'card_min_width' => ['int', 280, [200, 500]],
                'card_max_width' => ['int', 420, [280, 720]],
                'align'          => ['enum', 'left', ['left', 'center']],
                'gap'            => ['int', 14, [0, 40]],
                'font'           => ['enum', 'system', array_keys(self::FONTS)],
                'bg_transparent' => ['bool', true],
                'bg'             => ['color', '#ffffff'],
            ],
            'header' => [
                'header_show'   => ['bool', true],
                'header_text'   => ['text', '🏐 Ближайшие мероприятия', 60],
                'header_color'  => ['color', '@accent'],
                'header_size'   => ['int', 16, [12, 36]],
                'header_weight' => ['enum', '700', ['400', '600', '700']],
                'header_align'  => ['enum', 'left', ['left', 'center', 'right']],
            ],
            'card' => [
                'card_bg'           => ['color', '#ffffff'],
                'card_border'       => ['color', '#e5e7eb'],
                'card_border_width' => ['int', 1, [0, 4]],
                'card_radius'       => ['int', 14, [0, 32]],
                'card_shadow'       => ['enum', 'none', ['none', 'soft', 'strong']],
                'card_padding'      => ['int', 14, [8, 28]],
            ],
            'photo' => [
                'photo_show'  => ['bool', true],
                'photo_ratio' => ['enum', '16:9', ['16:9', '4:3', '1:1']],
                'photo_zoom'  => ['bool', true],
            ],
            'title' => [
                'title_color'  => ['color', '#1a1a1a'],
                'title_size'   => ['int', 16, [12, 28]],
                'title_weight' => ['enum', '700', ['400', '600', '700']],
            ],
            'meta' => [
                'meta_color'     => ['color', '#666666'],
                'meta_size'      => ['int', 14, [11, 20]],
                'show_icons'     => ['bool', true],
                'show_location'  => ['bool', true],
                'show_slots'     => ['bool', true],
                'show_level'     => ['bool', true],
            ],
            'badges' => [
                'show_direction'  => ['bool', true],
                'dir_classic'     => ['color', '#2967ba'],
                'dir_beach'       => ['color', '#e7612f'],
                'show_price'      => ['bool', true],
                'price_bg'        => ['color', '#000000'],
                'price_color'     => ['color', '#ffffff'],
                'badge_radius'    => ['int', 6, [0, 20]],
            ],
            'button' => [
                'button_show'   => ['bool', true],
                'button_text'   => ['text', 'Записаться на сайте', 40],
                'button_bg'     => ['color', '@accent'],
                'button_color'  => ['color', '#ffffff'],
                'button_radius' => ['int', 10, [0, 30]],
                'button_style'  => ['enum', 'filled', ['filled', 'outline']],
                'button_layout' => ['enum', 'flush', ['flush', 'inset']],
            ],
            'footer' => [
                'footer_color' => ['color', '#cccccc'],
            ],
        ];
    }

    /** Готовые темы: переопределяют часть полей (JS подставляет их в форму, на сервере хранятся итоговые значения). */
    public static function presets(): array
    {
        return [
            'light' => [],
            'dark' => [
                'bg_transparent' => false, 'bg' => '#161721',
                'header_color' => '#ffffff',
                'card_bg' => '#222333', 'card_border' => '#33344a',
                'title_color' => '#ffffff', 'meta_color' => '#b5b7c8',
                'footer_color' => '#6b6d80', 'card_shadow' => 'none',
            ],
            'sport' => [
                'header_color' => '#e7612f', 'header_size' => 20, 'header_weight' => '700',
                'card_radius' => 22, 'card_border_width' => 0, 'card_shadow' => 'strong',
                'title_size' => 18, 'button_bg' => '#e7612f', 'button_radius' => 14,
                'button_layout' => 'inset', 'badge_radius' => 10,
            ],
            'minimal' => [
                'layout' => 'list',
                'card_radius' => 6, 'card_border_width' => 1, 'card_shadow' => 'none',
                'title_weight' => '600',
                'button_style' => 'outline', 'button_radius' => 6, 'button_layout' => 'inset',
                'header_weight' => '600', 'header_size' => 15,
            ],
        ];
    }

    /** Значения по умолчанию; «@accent» — основной цвет виджета (settings.color, обратная совместимость). */
    public static function defaults(string $accent = '#f59e0b'): array
    {
        $out = [];
        foreach (self::groups() as $fields) {
            foreach ($fields as $key => $def) {
                $out[$key] = $def[1] === '@accent' ? $accent : $def[1];
            }
        }
        return $out;
    }

    /** Сохранённое + дефолты (для рендера и формы). */
    public static function resolve(?array $saved, string $accent = '#f59e0b'): array
    {
        $defaults = self::defaults($accent);
        $saved = $saved ?? [];
        // легаси-ключи до доработки раскладки (2026-10-06)
        if (isset($saved['columns']) && !isset($saved['columns_max'])) { $saved['columns_max'] = $saved['columns']; }
        if (isset($saved['meta_icons']) && !isset($saved['show_icons'])) { $saved['show_icons'] = $saved['meta_icons']; }
        return array_replace($defaults, self::sanitize($saved, $defaults, false));
    }

    /**
     * Проверка пользовательского ввода: всё, что не прошло, → значение из $defaults.
     * $fillMissing=false — отсутствующие ключи не добавляются (для resolve()).
     */
    public static function sanitize(array $input, array $defaults, bool $fillMissing = true): array
    {
        $out = [];
        foreach (self::groups() as $fields) {
            foreach ($fields as $key => $def) {
                if (!array_key_exists($key, $input)) {
                    if ($fillMissing) {
                        $out[$key] = $defaults[$key];
                    }
                    continue;
                }
                $v = $input[$key];
                [$type] = $def;

                $out[$key] = match ($type) {
                    'color' => (is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v)) ? strtolower($v) : $defaults[$key],
                    'int'   => is_numeric($v) ? max($def[2][0], min($def[2][1], (int) $v)) : $defaults[$key],
                    'enum'  => in_array((string) $v, array_map('strval', $def[2]), true) ? (is_int($def[1]) ? (int) $v : (string) $v) : $defaults[$key],
                    'bool'  => filter_var($v, FILTER_VALIDATE_BOOLEAN),
                    'text'  => self::cleanText($v, $def[2], $defaults[$key]),
                    default => $defaults[$key],
                };
            }
        }
        return $out;
    }

    private static function cleanText(mixed $v, int $max, string $fallback): string
    {
        if (!is_string($v)) {
            return $fallback;
        }
        $v = trim(strip_tags($v));
        $v = mb_substr($v, 0, $max);
        return $v === '' ? $fallback : $v;
    }

    /** Готовые темы поверхностей (theme != custom): перекрывают цвета полей при рендере, сохранённые поля не трогают. */
    public static function themes(): array
    {
        return [
            'light' => [
                'bg_transparent' => true, 'card_bg' => '#ffffff', 'card_border' => '#e5e7eb',
                'title_color' => '#1a1a1a', 'meta_color' => '#666666', 'footer_color' => '#9ca3af',
            ],
            // тёмный сайт, тёмные карточки, белый текст
            'dark' => [
                'bg_transparent' => true, 'card_bg' => '#222333', 'card_border' => '#33344a',
                'title_color' => '#ffffff', 'meta_color' => '#c3c5d6', 'header_color' => '#ffffff',
                'footer_color' => '#9a9cb0', 'price_bg' => '#000000',
            ],
            // тёмный сайт, светлые карточки
            'contrast' => [
                'bg_transparent' => true, 'card_bg' => '#ffffff', 'card_border' => '#ffffff',
                'title_color' => '#1a1a1a', 'meta_color' => '#555555', 'header_color' => '#ffffff',
                'footer_color' => '#c3c5d6',
            ],
        ];
    }

    /**
     * Итоговые значения для рендера: сохранённый стиль → тема → переопределения из data-* атрибутов
     * контейнера (layout, columns, theme, accent). Всё проходит ту же проверку, что и форма.
     */
    public static function forRender(array $style, array $override = []): array
    {
        $defaults = self::defaults($style['button_bg'] ?? '#f59e0b');
        $ov = self::sanitize(array_filter([
            'layout'      => $override['layout'] ?? null,
            'columns_max' => $override['columns'] ?? null,
            'theme'       => $override['theme'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''), $defaults, false);
        $style = array_replace($style, $ov);

        if (isset(self::themes()[$style['theme']])) {
            $style = array_replace($style, self::themes()[$style['theme']]);
        }

        $accent = (string) ($override['accent'] ?? '');
        if ($accent !== '' && preg_match('/^#?[0-9a-f]{6}$/i', $accent)) {
            $accent = '#' . ltrim(strtolower($accent), '#');
            $style['header_color'] = $accent;
            $style['button_bg']    = $accent;
        }

        return $style;
    }

    /**
     * CSS виджета. Все настраиваемые значения — через CSS-переменные `--vw-*` с серверным значением
     * в качестве запасного: `--_x: var(--vw-x, серверное)`. Переменные проходят сквозь shadow-root,
     * поэтому сайт может задать их на #volley-widget и перебить настройки панели.
     * $count — число карточек (ширина сетки ограничивается min(columns_max, count) * card_max).
     */
    public static function css(array $s, string $scope = '.vw-root', int $count = 3): string
    {
        $font   = self::FONTS[$s['font']] ?? self::FONTS['system'];
        $shadow = match ($s['card_shadow']) {
            'soft'   => '0 2px 10px rgba(0,0,0,.08)',
            'strong' => '0 8px 24px rgba(0,0,0,.18)',
            default  => 'none',
        };
        [$rw, $rh] = array_map('intval', explode(':', (string) $s['photo_ratio']) + [1 => 9]);
        $rw = max(1, $rw);
        $rh = max(1, $rh);

        $count  = max(1, $count);
        $cols   = max(1, min((int) $s['columns_max'], $count));
        $bg     = $s['bg_transparent'] ? 'transparent' : $s['bg'];
        $outline = $s['button_style'] === 'outline';
        $flush  = $s['button_layout'] === 'flush';
        $pad    = (int) $s['card_padding'];
        $list   = $s['layout'] === 'list';
        $wide   = $s['layout'] === 'wide';
        $margin = $s['align'] === 'center' ? '0 auto' : '0';
        $zoom   = $s['photo_zoom'] ? "$scope .vw-card:hover .vw-photo img{transform:scale(1.05)}" : '';

        $css = <<<CSS
:host{display:block}
$scope{
--_bg:var(--vw-bg,$bg);
--_card-bg:var(--vw-card-bg,{$s['card_bg']});
--_card-border:var(--vw-card-border,{$s['card_border']});
--_card-radius:var(--vw-card-radius,{$s['card_radius']}px);
--_title-color:var(--vw-title-color,{$s['title_color']});
--_meta-color:var(--vw-meta-color,{$s['meta_color']});
--_header-color:var(--vw-header-color,var(--vw-accent,{$s['header_color']}));
--_button-bg:var(--vw-button-bg,var(--vw-accent,{$s['button_bg']}));
--_button-color:var(--vw-button-color,{$s['button_color']});
--_dir-classic:var(--vw-dir-classic,{$s['dir_classic']});
--_dir-beach:var(--vw-dir-beach,{$s['dir_beach']});
--_footer-color:var(--vw-footer-color,{$s['footer_color']});
--_gap:var(--vw-gap,{$s['gap']}px);
--_card-min:var(--vw-card-min,{$s['card_min_width']}px);
--_card-max:var(--vw-card-max,{$s['card_max_width']}px);
--_wide-max:var(--vw-wide-max,760px);
container-type:inline-size;box-sizing:border-box;display:block;font-family:$font;background:var(--_bg);padding:12px;color:var(--_meta-color);line-height:1.35}
$scope *,$scope *::before,$scope *::after{box-sizing:border-box}
$scope a{text-decoration:none}
$scope a:focus-visible{outline:3px solid var(--_button-bg);outline-offset:2px;border-radius:4px}
$scope .vw-header{font-size:{$s['header_size']}px;font-weight:{$s['header_weight']};color:var(--_header-color);text-align:{$s['header_align']};margin:0 0 14px}
$scope .vw-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,var(--_card-min)),1fr));gap:var(--_gap);max-width:calc($cols * var(--_card-max) + {$cols} * var(--_gap) - var(--_gap));margin:$margin}
$scope .vw-card{display:flex;flex-direction:column;background:var(--_card-bg);border:{$s['card_border_width']}px solid var(--_card-border);border-radius:var(--_card-radius);box-shadow:$shadow;overflow:hidden}
$scope .vw-photo{display:block;position:relative;overflow:hidden;aspect-ratio:$rw / $rh;background:rgba(0,0,0,.06)}
$scope .vw-photo img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .6s ease}
$zoom
$scope .vw-badge{position:absolute;font-size:11px;font-weight:600;text-transform:uppercase;line-height:1;padding:6px 12px;border-radius:{$s['badge_radius']}px;color:#fff}
$scope .vw-badge-dir{right:8px;bottom:8px}
$scope .vw-badge-dir.classic{background:var(--_dir-classic)}
$scope .vw-badge-dir.beach{background:var(--_dir-beach)}
$scope .vw-badge-price{left:8px;top:8px;font-size:13px;text-transform:none;background:{$s['price_bg']}b0;color:{$s['price_color']};backdrop-filter:blur(8px)}
$scope .vw-dir-inline{position:static;display:inline-block;margin-bottom:8px}
$scope .vw-body{flex:1;padding:{$pad}px {$pad}px 8px}
$scope .vw-title{display:block;margin:0 0 8px;color:var(--_title-color);font-size:{$s['title_size']}px;font-weight:{$s['title_weight']};line-height:1.3}
$scope .vw-title:hover{opacity:.8}
$scope .vw-meta{display:flex;flex-direction:column;gap:5px;margin-bottom:6px}
$scope .vw-row{display:flex;align-items:flex-start;gap:6px;font-size:{$s['meta_size']}px;color:var(--_meta-color)}
$scope .vw-row .vw-ic{flex-shrink:0}
$scope .vw-row strong{color:var(--_title-color)}
$scope .vw-price-inline{display:inline-block;padding:1px 8px;border-radius:{$s['badge_radius']}px;background:{$s['price_bg']}1a;font-weight:600;color:var(--_title-color)}
$scope .vw-main{display:flex;flex-direction:column;flex:1;min-width:0}
$scope .vw-btn{display:block;text-align:center;font-size:14px;font-weight:700;border:2px solid var(--_button-bg);transition:opacity .15s;background:
CSS;
        $css .= $outline ? 'transparent;color:var(--_button-bg)}' : 'var(--_button-bg);color:var(--_button-color)}';
        $css .= <<<CSS

$scope .vw-btn:hover{opacity:.88}
$scope .vw-empty{text-align:center;padding:22px 16px;font-size:14px;color:var(--_meta-color);background:var(--_card-bg);border:1px dashed var(--_card-border);border-radius:var(--_card-radius)}
$scope .vw-footer{margin-top:14px;font-size:11px;text-align:right;color:var(--_footer-color)}
$scope .vw-footer a{color:var(--_footer-color)}
$scope .vw-lvl{display:inline-flex;align-items:center;height:20px;padding:0 8px;border-radius:10px;font-size:11px;font-weight:700;line-height:1;white-space:nowrap}
$scope .vw-lvl.l1{background:linear-gradient(#fff,#f0f0f0);color:#333;border:1px solid #ddd}
$scope .vw-lvl.l2{background:linear-gradient(#ffed4e,#ffd700);color:#8b6914}
$scope .vw-lvl.l3{background:linear-gradient(#ffa732,#ff8c00);color:#fff}
$scope .vw-lvl.l4{background:linear-gradient(#1e90ff,#0070e0);color:#fff}
$scope .vw-lvl.l5{background:linear-gradient(#ab8eff,#9370db);color:#fff}
$scope .vw-lvl.l6{background:linear-gradient(#ff4d4d,#f00);color:#fff}
$scope .vw-lvl.l7{background:linear-gradient(#333,#000);color:#fff}
CSS;

        // Кнопка: «впритык» к низу карточки или с отступами
        $css .= $flush
            ? "\n$scope .vw-btn{margin-top:auto;padding:11px;border-radius:0;border-width:" . ($outline ? '2px 0 0 0' : '0') . '}'
            : "\n$scope .vw-btn{margin:6px {$pad}px {$pad}px;padding:10px;border-radius:{$s['button_radius']}px}";

        // Широкая (горизонтальная) карточка: фото слева, текст справа, кнопка справа внизу
        $horizontal = "$scope .vw-card.has-photo{flex-direction:row}"
            . "$scope .vw-card.has-photo .vw-photo{width:42%;flex-shrink:0;aspect-ratio:auto;min-height:170px}"
            . "$scope .vw-card .vw-btn{align-self:flex-end;margin:6px {$pad}px {$pad}px auto;padding:10px 22px;border-width:2px;border-radius:{$s['button_radius']}px}";

        if ($list) {
            // Список строк без фото: заголовок и данные слева, кнопка справа
            $css .= "\n$scope .vw-cards{grid-template-columns:1fr;max-width:none}"
                . "\n@container (min-width:520px){{$scope} .vw-main{flex-direction:row;align-items:center}{$scope} .vw-body{padding-bottom:{$pad}px}"
                . "{$scope} .vw-card .vw-btn{align-self:center;margin:{$pad}px {$pad}px {$pad}px 0;padding:10px 22px;border-width:2px;border-radius:{$s['button_radius']}px;white-space:nowrap}}";
        } elseif ($wide) {
            $css .= "\n$scope .vw-cards{grid-template-columns:1fr;max-width:var(--_wide-max)}"
                . "\n@container (min-width:560px){{$horizontal}}";
        } elseif ($count === 1) {
            // Одно мероприятие: узкая карточка по умолчанию, широкая — когда блок ≥ 560px
            $css .= "\n@container (min-width:560px){{$scope} .vw-cards{grid-template-columns:1fr;max-width:var(--_wide-max)}{$horizontal}}";
        }

        return $css;
    }
}
