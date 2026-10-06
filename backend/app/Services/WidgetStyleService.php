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
                'layout'   => ['enum', 'cards', ['cards', 'list']],
                'columns'  => ['int', 3, [1, 4]],
                'gap'      => ['int', 14, [0, 40]],
                'font'     => ['enum', 'system', array_keys(self::FONTS)],
                'bg_transparent' => ['bool', true],
                'bg'       => ['color', '#ffffff'],
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
                'meta_icons'     => ['bool', true],
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
                'layout' => 'list', 'columns' => 1,
                'card_radius' => 6, 'card_border_width' => 1, 'card_shadow' => 'none',
                'photo_show' => false, 'title_weight' => '600',
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
        return array_replace($defaults, self::sanitize($saved ?? [], $defaults, false));
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

    public static function css(array $s, string $scope = '.vw-root'): string
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

        $cols = (int) $s['columns'];
        $cols2 = min($cols, 2);
        $list = $s['layout'] === 'list';
        $bg   = $s['bg_transparent'] ? 'transparent' : $s['bg'];
        $btnBg = $s['button_style'] === 'outline' ? 'transparent' : $s['button_bg'];
        $btnFg = $s['button_style'] === 'outline' ? $s['button_bg'] : $s['button_color'];
        $btnBorder = $s['button_bg'];
        $flush = $s['button_layout'] === 'flush';
        $r = (int) $s['card_radius'];
        $pad = (int) $s['card_padding'];
        $zoom = $s['photo_zoom'] ? "$scope .vw-card:hover .vw-photo img{transform:scale(1.05)}" : '';

        $css = <<<CSS
$scope{container-type:inline-size;box-sizing:border-box;display:block;font-family:$font;background:$bg;padding:12px;color:{$s['meta_color']};line-height:1.35}
$scope *,$scope *::before,$scope *::after{box-sizing:border-box}
$scope a{text-decoration:none}
$scope .vw-header{font-size:{$s['header_size']}px;font-weight:{$s['header_weight']};color:{$s['header_color']};text-align:{$s['header_align']};margin:0 0 14px}
$scope .vw-cards{display:grid;grid-template-columns:repeat($cols,minmax(0,1fr));gap:{$s['gap']}px}
$scope .vw-card{display:flex;flex-direction:column;background:{$s['card_bg']};border:{$s['card_border_width']}px solid {$s['card_border']};border-radius:{$r}px;box-shadow:$shadow;overflow:hidden}
$scope .vw-photo{display:block;position:relative;overflow:hidden;aspect-ratio:$rw / $rh;background:rgba(0,0,0,.06)}
$scope .vw-photo img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .6s ease}
$zoom
$scope .vw-badge{position:absolute;font-size:11px;font-weight:600;text-transform:uppercase;line-height:1;padding:6px 12px;border-radius:{$s['badge_radius']}px;color:#fff}
$scope .vw-badge-dir{right:8px;bottom:8px}
$scope .vw-badge-dir.classic{background:{$s['dir_classic']}}
$scope .vw-badge-dir.beach{background:{$s['dir_beach']}}
$scope .vw-badge-price{left:8px;top:8px;font-size:13px;text-transform:none;background:{$s['price_bg']}b0;color:{$s['price_color']};backdrop-filter:blur(8px)}
$scope .vw-dir-inline{position:static;display:inline-block;margin-bottom:8px}
$scope .vw-body{flex:1;padding:{$pad}px {$pad}px 8px}
$scope .vw-title{display:block;margin:0 0 8px;color:{$s['title_color']};font-size:{$s['title_size']}px;font-weight:{$s['title_weight']};line-height:1.3}
$scope .vw-title:hover{opacity:.8}
$scope .vw-meta{display:flex;flex-direction:column;gap:5px;margin-bottom:6px}
$scope .vw-row{display:flex;align-items:flex-start;gap:6px;font-size:{$s['meta_size']}px;color:{$s['meta_color']}}
$scope .vw-row .vw-ic{flex-shrink:0}
$scope .vw-row strong{color:{$s['title_color']}}
$scope .vw-price-inline{display:inline-block;padding:1px 8px;border-radius:{$s['badge_radius']}px;background:{$s['price_bg']}1a;font-weight:600;color:{$s['title_color']}}
$scope .vw-btn{display:block;text-align:center;font-size:14px;font-weight:700;background:$btnBg;color:$btnFg;border:2px solid $btnBorder;transition:opacity .15s}
$scope .vw-btn:hover{opacity:.88}
$scope .vw-empty{text-align:center;padding:20px 0;font-size:14px;opacity:.7}
$scope .vw-footer{margin-top:14px;font-size:11px;text-align:right;color:{$s['footer_color']}}
$scope .vw-footer a{color:{$s['footer_color']}}
$scope .vw-lvl{display:inline-flex;align-items:center;height:20px;padding:0 8px;border-radius:10px;font-size:11px;font-weight:700;line-height:1;white-space:nowrap}
$scope .vw-lvl.l1{background:linear-gradient(#fff,#f0f0f0);color:#333;border:1px solid #ddd}
$scope .vw-lvl.l2{background:linear-gradient(#ffed4e,#ffd700);color:#8b6914}
$scope .vw-lvl.l3{background:linear-gradient(#ffa732,#ff8c00);color:#fff}
$scope .vw-lvl.l4{background:linear-gradient(#1e90ff,#0070e0);color:#fff}
$scope .vw-lvl.l5{background:linear-gradient(#ab8eff,#9370db);color:#fff}
$scope .vw-lvl.l6{background:linear-gradient(#ff4d4d,#f00);color:#fff}
$scope .vw-lvl.l7{background:linear-gradient(#333,#000);color:#fff}
CSS;

        // Кнопка: «впритык» к низу карточки (обрезается скруглением карточки) или с отступами
        if ($flush) {
            $css .= "\n$scope .vw-btn{margin-top:auto;padding:11px;border-radius:0;border-width:"
                . ($s['button_style'] === 'outline' ? '2px 0 0 0' : '0') . '}';
        } else {
            $css .= "\n$scope .vw-btn{margin:6px {$pad}px {$pad}px;padding:10px;border-radius:{$s['button_radius']}px}";
        }

        if ($list) {
            $css .= "\n$scope .vw-cards{grid-template-columns:1fr}"
                . "\n$scope .vw-card.has-photo{flex-direction:row}"
                . "\n$scope .vw-card.has-photo .vw-photo{width:38%;flex-shrink:0;aspect-ratio:auto;min-height:150px}"
                . "\n$scope .vw-card.has-photo .vw-main{display:flex;flex-direction:column;flex:1;min-width:0}"
                . "\n@container (max-width:520px){{$scope} .vw-card.has-photo{flex-direction:column}{$scope} .vw-card.has-photo .vw-photo{width:100%;aspect-ratio:$rw / $rh;min-height:0}}";
        } else {
            $css .= "\n$scope .vw-main{display:flex;flex-direction:column;flex:1}"
                . "\n@container (max-width:900px){{$scope} .vw-cards{grid-template-columns:repeat($cols2,minmax(0,1fr))}}"
                . "\n@container (max-width:560px){{$scope} .vw-cards{grid-template-columns:1fr}}";
        }

        return $css;
    }
}
