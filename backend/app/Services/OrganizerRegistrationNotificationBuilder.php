<?php

namespace App\Services;

use App\Data\ChannelMessageData;
use App\Models\EventOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrganizerRegistrationNotificationBuilder
{
    private const POS_LABELS = [
        'setter'   => 'Связующий',
        'outside'  => 'Доигровщик',
        'opposite' => 'Диагональный',
        'middle'   => 'Центральный',
        'libero'   => 'Либеро',
        'defender' => 'Защитник',
    ];

    public function __construct(
        private NotificationTemplateService  $templateService,
        private NotificationTemplateRenderer $templateRenderer,
    ) {}

    /**
     * @param  string $type  'registered' | 'cancelled'
     */
    public function build(
        EventOccurrence $occurrence,
        User $player,
        string $type,
        string $platform = 'telegram'
    ): ChannelMessageData {
        $templateCode = $type === 'registered'
            ? 'organizer_player_registered'
            : 'organizer_player_cancelled';

        $data = $this->buildTemplateData($occurrence, $player);

        $tpl = $this->templateService->findActiveTemplate($templateCode);

        if ($tpl) {
            $title     = $this->templateRenderer->render($tpl->title_template, $data) ?? '';
            $body      = $this->templateRenderer->render($tpl->body_template, $data) ?? '';
            $btnText   = $tpl->button_text ? ($this->templateRenderer->render($tpl->button_text, $data) ?? $tpl->button_text) : 'Открыть мероприятие';
            $btnUrl    = $tpl->button_url_template ? ($this->templateRenderer->render($tpl->button_url_template, $data) ?? null) : ($data['event_url'] ?? null);
        } else {
            $title   = $type === 'registered' ? '✅ Регистрация подтверждена' : '⛔️ Бронь отменена';
            $body    = $this->buildFallbackText($data, $type);
            $btnText = 'Открыть мероприятие';
            $btnUrl  = $data['event_url'] ?? null;
        }

        return new ChannelMessageData(
            title: $title,
            text: $body,
            buttonUrl: $btnUrl,
            buttonText: $btnText,
        );
    }

    private function buildTemplateData(EventOccurrence $occurrence, User $player): array
    {
        $event    = $occurrence->event;
        $location = $event->location ?? null;
        $tz       = $occurrence->timezone ?: ($event->timezone ?: 'UTC');

        $starts      = Carbon::parse($occurrence->starts_at, 'UTC')->setTimezone($tz);
        $durationSec = $occurrence->duration_sec ?? $event->duration_sec ?? null;
        $ends        = $durationSec ? $starts->copy()->addSeconds((int) $durationSec) : null;

        Carbon::setLocale('ru');
        $dayName = mb_ucfirst($starts->translatedFormat('l'));
        $dateStr = $starts->translatedFormat('j F');
        $timeStr = $starts->format('H:i') . ($ends ? ' - ' . $ends->format('H:i') : '');

        $addrParts = array_filter([
            $location->metro   ?? null,
            $location->city?->name ?? null,
            $location->address ?? null,
        ]);
        $locationFull = $addrParts ? implode(', ', $addrParts) : ($location->name ?? '');

        $registered  = $this->countRegistered((int) $occurrence->id);
        $maxPlayers  = $this->getMaxPlayers($occurrence);
        $available   = $maxPlayers > 0 ? max(0, $maxPlayers - $registered) : 0;

        $playerName = trim(implode(' ', array_filter([
            $player->last_name,
            $player->first_name,
            $player->patronymic,
        ])));
        if ($playerName === '') {
            $playerName = (string) ($player->name ?? '—');
        }

        $positionCode  = $this->getPlayerPosition($player->id, $occurrence->id);
        $positionLabel = $positionCode ? (self::POS_LABELS[$positionCode] ?? $positionCode) : '';

        $occUrl = route('events.show', [
            'event'      => (int) $event->id,
            'occurrence' => (int) $occurrence->id,
        ]);

        return [
            'event_title'      => (string) ($event->title ?? ''),
            'event_date'       => "{$dayName}, {$dateStr}",
            'event_time'       => $timeStr,
            'location_full'    => $locationFull,
            'booked_count'     => (string) $registered,
            'available_count'  => (string) $available,
            'player_name'      => $playerName,
            'player_phone'     => (string) ($player->phone ?? ''),
            'player_position'  => $positionLabel,
            'event_url'        => $occUrl,
        ];
    }

    private function buildFallbackText(array $data, string $type): string
    {
        $header = $type === 'registered' ? '✅ Регистрация подтверждена' : '⛔️ Бронь отменена';
        $lines  = [
            $header, '',
            "🏐 {$data['event_title']}", '',
            'Информация:', '',
            "📆: {$data['event_date']}",
            "🕘: {$data['event_time']}",
        ];
        if ($data['location_full'] !== '') {
            $lines[] = "📍: {$data['location_full']}";
        }
        $lines[] = '';
        $lines[] = "Сейчас {$data['booked_count']} мест(о) забронировано, а {$data['available_count']} доступно.";
        $lines[] = '';
        $lines[] = 'Детали записи:';
        $lines[] = '';
        $lines[] = "👤 : {$data['player_name']}";
        if ($data['player_phone'] !== '') {
            $lines[] = "☎️ : {$data['player_phone']}";
        }
        if ($data['player_position'] !== '') {
            $lines[] = "✅ {$data['player_position']}";
        }
        return implode("\n", $lines);
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

    private function getMaxPlayers(EventOccurrence $occurrence): int
    {
        return (int) ($occurrence->max_players
            ?? $occurrence->event?->gameSettings?->max_players
            ?? 0);
    }

    private function getPlayerPosition(int $userId, int $occurrenceId): ?string
    {
        $val = DB::table('event_registrations')
            ->where('user_id', $userId)
            ->where('occurrence_id', $occurrenceId)
            ->whereNull('cancelled_at')
            ->value('position');

        return $val !== null ? (string) $val : null;
    }
}
