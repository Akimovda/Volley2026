<?php

namespace App\Services;

use App\Data\ChannelMessageData;
use App\Models\EventOccurrence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OccurrenceAnnouncementMessageBuilder
{
    public function build(EventOccurrence $occurrence, array $options = []): ChannelMessageData
    {
        $event    = $occurrence->event;
        $tz       = $occurrence->timezone ?: ($event->timezone ?: 'UTC');
        $platform = (string) ($options['platform'] ?? '');

        $starts = Carbon::parse($occurrence->starts_at, 'UTC')->setTimezone($tz);

        // Время окончания
        $durationSec = $occurrence->duration_sec ?? $event->duration_sec ?? null;
        $ends = $durationSec ? $starts->copy()->addSeconds((int) $durationSec) : null;

        // Ссылка
        $link = !empty($options['private_link'])
            ? (string) $options['private_link']
            : route('events.show', ['event' => $event->id, 'occurrence' => $occurrence->id]);

        // Картинка
        $imageUrl = null;
        if ((bool) ($options['include_image'] ?? true)) {
            // 1. Сначала ищем cover (загруженный файл напрямую на Event)
            $media = $event->media?->first();
        
            if ($media) {
                $imageUrl = $media->getUrl();
            } else {
                // 2. Потом ищем из галереи организатора (event_photos — JSON массив ID)
                $photoIds = $event->event_photos ?? [];
                if (is_string($photoIds)) {
                    $photoIds = json_decode($photoIds, true) ?: [];
                }
        
                if (!empty($photoIds)) {
                    $galleryMedia = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($photoIds[0]);
                    if ($galleryMedia) {
                        $imageUrl = $galleryMedia->getUrl();
                    }
                }
            }
        
            // 3. Фолбэк — дефолтная картинка по направлению
            if (!$imageUrl) {
                $direction = (string) ($event->direction ?? 'classic');
                $appUrl    = rtrim((string) config('app.url'), '/');
                $imageUrl  = $direction === 'beach'
                    ? $appUrl . '/img/beach.webp'
                    : $appUrl . '/img/classic.webp';
            }
        }
        // Для MAX конвертируем WebP → JPEG
        if (!empty($imageUrl) && ($options['platform'] ?? '') === 'max') {
            $imageUrl = $this->convertWebpToJpegUrl($imageUrl) ?? null;
        }
        // Текст анонса
        $text = $this->buildText($occurrence, $options, $starts, $ends, $tz, $platform);

        return new ChannelMessageData(
            title:      (string) $event->title,
            text:       $text,
            buttonUrl:  $link,
            buttonText: 'Записаться!',
            imageUrl:   $imageUrl,
            silent:     (bool) ($options['silent'] ?? false),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function buildText(
        EventOccurrence $occurrence,
        array $options,
        Carbon $starts,
        ?Carbon $ends,
        string $tz,
        string $platform
    ): string {
        $event     = $occurrence->event;
        $location  = $event->location;
        $organizer = $event->organizer;

        $lines = [];

        // ── Название ──────────────────────────────────────────────────────────
        $lines[] = $this->bold($platform, (string) $event->title);
        $lines[] = '';

        // ── Дата ─────────────────────────────────────────────────────────────
        $dateStr = $starts->translatedFormat('j F'); // «6 апреля»
        $lines[] = "🗓 {$dateStr}";

        // ── Время ─────────────────────────────────────────────────────────────
        $timeStr = $starts->format('H:i');
        if ($ends) {
            $timeStr .= '–' . $ends->format('H:i');
        }
        $tzLabel    = $this->formatTimezone($tz);
        $durationStr = $ends ? $this->formatDuration($starts, $ends) : '';
        $timeLine = "⏰ {$timeStr} ({$tzLabel})";
        if ($durationStr) {
            $timeLine .= " ⏳ {$durationStr}";
        }
        $lines[] = $timeLine;

        // ── Адрес ─────────────────────────────────────────────────────────────
        if ($location) {
            $addrParts = array_filter([
                $location->metro ?? null,
                $location->city?->name ?? null,
                $location->address ?? null,
            ]);
            $addr = $addrParts ? implode(', ', $addrParts) : ($location->name ?? null);
            if ($addr) {
                $lines[] = "📍 {$addr}";
            }
        }

        // ── Организатор ───────────────────────────────────────────────────────
        if ($organizer) {
            $orgName = trim((string) ($organizer->name ?? ''));
            if ($orgName !== '') {
                $lines[] = "👤 Организатор: {$orgName}";
            }
        }

        $lines[] = '';

        // ── Формат ────────────────────────────────────────────────────────────
        $gameSettings = $this->loadGameSettings((int) $event->id);
        $subtype      = (string) ($gameSettings['subtype'] ?? '');
        $direction    = (string) ($event->direction ?? 'classic');

        $formatLabel = $this->formatLabel($direction, $subtype);
        if ($formatLabel !== '') {
            $lines[] = "🏐 Формат: {$formatLabel}";
        }

        // ── Уровень ───────────────────────────────────────────────────────────
        $levelLine = $this->buildLevelLine($event, $direction);
        if ($levelLine !== '') {
            $lines[] = "📈 Уровень: {$levelLine}";
        }

        // ── Цена ──────────────────────────────────────────────────────────────
        if ($event->is_paid && $event->price_minor > 0) {
            $amount   = number_format($event->price_minor / 100, 2, ',', ' ');
            $currency = $this->currencySymbol((string) ($event->price_currency ?? 'RUB'));
            $lines[] = "💸 {$amount} {$currency}";
        }

        $lines[] = '';

        // ── Мест осталось ─────────────────────────────────────────────────────
        $maxPlayers  = (int) ($gameSettings['max_players'] ?? 0);
        $registered  = $this->countRegistered((int) $occurrence->id);
        $free        = $maxPlayers > 0 ? max(0, $maxPlayers - $registered) : null;

        if ($maxPlayers > 0) {
            $freeLabel = $free === 0
                ? "Мест нет 🔴"
                : ($free <= 3 ? "Осталось мест: {$free} из {$maxPlayers} 🟡" : "Осталось мест: {$free} из {$maxPlayers}!");
            $lines[] = "🧑‍🧑‍🧒 {$freeLabel}";
        }

        // ── Список игроков ────────────────────────────────────────────────────
        $includeList = (bool) ($options['include_registered_list'] ?? true);
        if ($includeList && $registered > 0) {
            $playerList = $this->buildPlayerList((int) $occurrence->id);
            if ($playerList !== '') {
                $lines[] = '';
                $lines[] = $this->bold($platform, 'Список игроков:');
                $lines[] = $playerList;
            }
        }

        return implode("\n", $lines);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function bold(string $platform, string $text): string
    {
        if ($platform === 'telegram') {
            return "<b>{$text}</b>";
        }
        return $text;
    }
    /**
     * Конвертирует WebP URL → JPEG для платформ которые не поддерживают WebP (MAX).
     * Скачивает файл, конвертирует через GD и сохраняет во временный публичный файл.
     */
    private function convertWebpToJpegUrl(string $url): ?string
    {
        if (!str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.webp')) {
            return $url;
        }
    
        try {
            $cacheDir = public_path('img/converted/');
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
    
            $cacheFile = $cacheDir . md5($url) . '.jpg';
            $cacheUrl  = rtrim(config('app.url'), '/') . '/img/converted/' . md5($url) . '.jpg';
    
            // Уже конвертировали — отдаём кэш
            if (file_exists($cacheFile)) {
                return $cacheUrl;
            }
    
            // Если это локальный файл — читаем напрямую, иначе скачиваем
            $localPath = public_path(parse_url($url, PHP_URL_PATH));
            if (file_exists($localPath)) {
                $img = imagecreatefromwebp($localPath);
            } else {
                $content = file_get_contents($url);
                if (!$content) return null;
                $tmpIn = tempnam(sys_get_temp_dir(), 'webp_') . '.webp';
                file_put_contents($tmpIn, $content);
                $img = imagecreatefromwebp($tmpIn);
                unlink($tmpIn);
            }
    
            if (!$img) return null;
            imagejpeg($img, $cacheFile, 90);
            imagedestroy($img);
    
            return $cacheUrl;
        } catch (\Throwable $e) {
            \Log::warning('WebP→JPEG failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Метка формата: «4×2», «2×2», «Тренировка» и т.д.
     */
    private function formatLabel(string $direction, string $subtype): string
    {
        $subtypeMap = [
            '4x4'        => '4×4',
            '4x2'        => '4×2',
            '5x1'        => '5×1',
            '5x1_libero' => '5×1 с либеро',
            '2x2'        => '2×2',
            '3x3'        => '3×3',
        ];

        if ($subtype !== '' && isset($subtypeMap[$subtype])) {
            return $subtypeMap[$subtype];
        }

        return match ($direction) {
            'beach'   => 'Пляжный волейбол',
            'classic' => 'Классический волейбол',
            default   => '',
        };
    }

    /**
     * Строка уровня: «1 ⚪️ – 4 🔵»
     */
    private function buildLevelLine(object $event, string $direction): string
    {
        $minField = $direction === 'beach' ? 'beach_level_min' : 'classic_level_min';
        $maxField = $direction === 'beach' ? 'beach_level_max' : 'classic_level_max';

        $min = $event->$minField ?? null;
        $max = $event->$maxField ?? null;

        if ($min === null && $max === null) {
            return '';
        }

        $fmt = fn ($v) => $v . ' ' . $this->levelEmoji((int) $v);

        if ($min !== null && $max !== null) {
            return $fmt($min) . ' – ' . $fmt($max);
        }

        return $fmt($min ?? $max);
    }

    private function levelEmoji(int $level): string
    {
        return match ($level) {
            1 => '⚪️',
            2 => '🟡',
            3 => '🟠',
            4 => '🔵',
            5 => '🟢',
            6 => '🔴',
            7 => '🟣',
            default => '',
        };
    }

    /**
     * Форматирует timezone: «Europe/Moscow» → «MSK (UTC+03:00)»
     */
    private function formatTimezone(string $tz): string
    {
        try {
            $dt     = new \DateTime('now', new \DateTimeZone($tz));
            $offset = $dt->getOffset();
            $sign   = $offset >= 0 ? '+' : '-';
            $h      = abs((int) floor($offset / 3600));
            $m      = abs((int) (($offset % 3600) / 60));
            $utc    = sprintf('UTC%s%02d:%02d', $sign, $h, $m);

            // Попытка дать короткий псевдоним
            $abbr = $dt->format('T');
            if (preg_match('/^[A-Z]{2,5}$/', $abbr)) {
                return "{$abbr} ({$utc})";
            }

            return $utc;
        } catch (\Throwable) {
            return $tz;
        }
    }

    /**
     * Длительность: «2:00», «1:30»
     */
    private function formatDuration(Carbon $starts, Carbon $ends): string
    {
        $mins = (int) $starts->diffInMinutes($ends);
        if ($mins <= 0) return '';
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return $m > 0 ? sprintf('%d:%02d', $h, $m) : "{$h}:00";
    }

    private function currencySymbol(string $code): string
    {
        return match ($code) {
            'RUB' => '₽',
            'USD' => '$',
            'EUR' => '€',
            'KZT' => '₸',
            default => $code,
        };
    }

    private function countRegistered(int $occurrenceId): int
    {
        return (int) DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->whereNull('cancelled_at')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'confirmed');
            })
            ->count();
    }

    /**
     * Список игроков с позициями.
     * Боты (is_bot = true) не показываются в публичном списке.
     */
    private function buildPlayerList(int $occurrenceId): string
    {
        $posLabels = [
            'setter'   => 'связующий',
            'outside'  => 'доигровщик',
            'opposite' => 'диагональный',
            'middle'   => 'центральный',
            'libero'   => 'либеро',
        ];

        $rows = DB::table('event_registrations as er')
            ->join('users as u', 'u.id', '=', 'er.user_id')
            ->where('er.occurrence_id', $occurrenceId)
            ->whereNull('er.cancelled_at')
            ->where(function ($q) {
                $q->whereNull('er.status')->orWhere('er.status', 'confirmed');
            })
            ->where(function ($q) {
                // Скрываем ботов из публичного списка
                $q->whereNull('u.is_bot')->orWhere('u.is_bot', false);
            })
            ->orderBy('er.id')
            ->limit(30)
            ->get(['u.name', 'u.first_name', 'u.last_name', 'er.position']);

        if ($rows->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($rows as $i => $r) {
            $firstName = trim((string) ($r->first_name ?? ''));
            $lastName  = trim((string) ($r->last_name ?? ''));
            $fullName  = trim($lastName . ' ' . $firstName);
            $name      = $fullName !== '' ? $fullName : trim((string) ($r->name ?? 'Игрок'));

            $pos      = (string) ($r->position ?? '');
            $posLabel = $posLabels[$pos] ?? ($pos !== '' ? $pos : null);

            $line = ($i + 1) . '. ' . $name;
            if ($posLabel !== null) {
                $line .= ' - ' . $posLabel;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function loadGameSettings(int $eventId): array
    {
        $row = DB::table('event_game_settings')
            ->where('event_id', $eventId)
            ->first(['subtype', 'min_players', 'max_players', 'gender_policy', 'libero_mode']);

        return $row ? (array) $row : [];
    }
}