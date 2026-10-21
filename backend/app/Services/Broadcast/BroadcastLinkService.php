<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastLink;
use App\Models\Event;
use App\Models\EventCourt;
use App\Models\User;
use App\Services\EventAccessService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Выдача/отзыв токен-ссылок. Создают и отзывают владелец, staff и админ (canManageEvent). */
class BroadcastLinkService
{
    public function __construct(protected EventAccessService $access) {}

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

        return $this->create($by, $link->event, $link->role, $link->court, $link->label, $link->expires_at);
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
