<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastLink;
use App\Models\Event;
use App\Models\EventCourt;
use App\Models\User;
use App\Services\EventAccessService;
use App\Services\UserNotificationService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Выдача/отзыв токен-ссылок. Создают и отзывают владелец, staff и админ (canManageEvent). */
class BroadcastLinkService
{
    public function __construct(
        protected EventAccessService $access,
        protected CourtStateService $courtState,
    ) {}

    public function create(
        User $by,
        Event $event,
        string $role,
        ?EventCourt $court = null,
        ?string $label = null,
        ?Carbon $expiresAt = null,
    ): BroadcastLink {
        $this->authorize($by, $event);

        if (!in_array($role, BroadcastLink::ROLES, true)) {
            throw new InvalidArgumentException('Неизвестная роль ссылки.');
        }
        if ($court && (int) $court->event_id !== (int) $event->id) {
            throw new InvalidArgumentException('Площадка принадлежит другому мероприятию.');
        }
        // Оверлей показывает одну площадку — без неё ссылка бессмысленна
        if ($role === BroadcastLink::ROLE_OVERLAY && !$court) {
            throw new InvalidArgumentException('Для оверлея нужна площадка.');
        }

        return BroadcastLink::create([
            'event_id'           => $event->id,
            'occurrence_id'      => $court?->occurrence_id,
            'court_id'           => $court?->id,
            'role'               => $role,
            'token'              => $this->newToken(),
            'label'              => $label,
            'created_by_user_id' => $by->id,
            'expires_at'         => $expiresAt,
        ]);
    }

    public function revoke(User $by, BroadcastLink $link): BroadcastLink
    {
        $this->authorize($by, $link->event);
        if ($link->revoked_at === null) {
            $link->forceFill(['revoked_at' => now()])->save();
        }
        return $link;
    }

    /** Отзывает старую ссылку и выдаёт новую с теми же параметрами. */
    public function regenerate(User $by, BroadcastLink $link): BroadcastLink
    {
        $this->authorize($by, $link->event);
        $this->revoke($by, $link);

        if ($link->role === BroadcastLink::ROLE_SCORER) {
            return $this->createScorer($by, $link->event, $link->court, $link->assignedUser);
        }

        return $this->create($by, $link->event, $link->role, $link->court, $link->label, $link->expires_at);
    }

    /**
     * Ссылка ведущего площадки. Срок — до конца дня последнего матча площадки + 12 ч (в часовом поясе мероприятия).
     * $assignee — приглашение пользователя (ссылка открывается только под его аккаунтом, ему уходит уведомление).
     */
    public function createScorer(User $by, Event $event, EventCourt $court, ?User $assignee = null, bool $notify = false): BroadcastLink
    {
        $this->authorize($by, $event);
        if ((int) $court->event_id !== (int) $event->id) {
            throw new InvalidArgumentException('Площадка принадлежит другому мероприятию.');
        }

        $link = BroadcastLink::create([
            'event_id'           => $event->id,
            'occurrence_id'      => $court->occurrence_id,
            'court_id'           => $court->id,
            'role'               => BroadcastLink::ROLE_SCORER,
            'token'              => $this->newToken(),
            'created_by_user_id' => $by->id,
            'assigned_user_id'   => $assignee?->id,
            'expires_at'         => $this->scorerExpiry($event, $court),
        ]);

        if ($assignee && $notify) {
            $this->notifyInvited($assignee, $event, $court, $link);
        }

        return $link;
    }

    /** До конца дня последнего матча площадки + 12 ч; матчей нет — от даты тура, иначе от сегодняшнего дня. */
    public function scorerExpiry(Event $event, EventCourt $court): Carbon
    {
        $court->loadMissing('occurrence');
        $tz = $court->occurrence?->timezone ?: ($event->timezone ?: 'Europe/Moscow');

        $last = $this->courtState->matchesOfCourt($court)->max('scheduled_at');
        $base = $last ? Carbon::parse($last) : ($court->occurrence?->starts_at ? Carbon::parse($court->occurrence->starts_at) : Carbon::now());

        return $base->copy()->setTimezone($tz)->endOfDay()->addHours(12)->setTimezone('UTC');
    }

    public function notifyInvited(User $assignee, Event $event, EventCourt $court, BroadcastLink $link): void
    {
        try {
            app(UserNotificationService::class)->createBroadcastScorerInvitedNotification(
                (int) $assignee->id, (int) $event->id, (string) $event->title, (string) $court->name,
                route('scorer.hub', $link->token),
            );
        } catch (\Throwable $e) {
            \Log::warning('Scorer invite notification failed: ' . $e->getMessage());
        }
    }

    private function authorize(User $by, Event $event): void
    {
        if (!$this->access->canManageEvent($by, (int) $event->organizer_id)) {
            throw new AuthorizationException('Нет прав на управление трансляцией этого мероприятия.');
        }
    }

    private function newToken(): string
    {
        do {
            $t = Str::random(40);
        } while (BroadcastLink::where('token', $t)->exists());

        return $t;
    }
}
