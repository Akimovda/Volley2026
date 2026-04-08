<?php

namespace App\Services;

use App\Jobs\SendNotificationDeliveryJob;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\UserNotification;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UserNotificationService
{
    public function __construct(
        private NotificationTemplateService $templateService,
        private NotificationTemplateRenderer $templateRenderer,
        private NotificationTemplateDataBuilder $templateDataBuilder
    ) {}

    public function create(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?array $payload = null,
        array $channels = ['in_app']
    ): UserNotification {
        return DB::transaction(function () use ($userId, $type, $title, $body, $payload, $channels) {
            $user = User::query()->find($userId);
            if (!$user) {
                throw new DomainException('Пользователь для уведомления не найден.');
            }

            $payload = $payload ?? [];

            $rendered = $this->resolveRenderedContent(
                type: $type,
                user: $user,
                payload: $payload,
                fallbackTitle: $title,
                fallbackBody: $body
            );

            // Базовый payload для хранения в БД (без template_data — он большой)
            $notificationPayload = array_merge($payload, [
                'title'         => $rendered['title'],
                'body'          => $rendered['body'],
                'image_url'     => $rendered['image_url'],
                'button_text'   => $rendered['button_text'],
                'button_url'    => $rendered['button_url'],
                'template_code' => $type,
            ]);

            $notification = UserNotification::query()->create([
                'user_id'  => $userId,
                'type'     => $type,
                'title'    => $rendered['title'],
                'body'     => $rendered['body'],
                'payload'  => $notificationPayload,
                'read_at'  => null,
            ]);

            $channels = $this->normalizeChannels($channels, $user);

            foreach ($channels as $channel) {
                // Для внешних каналов добавляем структурированные данные (дата, адрес и т.д.)
                // чтобы NotificationDeliverySender мог строить красивое сообщение
                $deliveryPayload = array_merge(
                    $channel !== 'in_app' ? ($rendered['template_data'] ?? []) : [],
                    $notificationPayload,
                    [
                        'user_notification_id' => $notification->id,
                        'channel'              => $channel,
                    ]
                );

                $deliveryId = (int) DB::table('notification_deliveries')->insertGetId([
                    'event_id'      => $payload['event_id'] ?? null,
                    'occurrence_id' => $payload['occurrence_id'] ?? null,
                    'user_id'       => $userId,
                    'type'          => $type,
                    'channel'       => $channel,
                    'status'        => $channel === 'in_app' ? 'sent' : 'pending',
                    'scheduled_at'  => now(),
                    'sent_at'       => $channel === 'in_app' ? now() : null,
                    'dedupe_key'    => $this->makeDedupeKey($notification->id, $channel, $type),
                    'payload'       => json_encode($deliveryPayload, JSON_UNESCAPED_UNICODE),
                    'error'         => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                if (in_array($channel, ['telegram', 'vk', 'max'], true)) {
                    SendNotificationDeliveryJob::dispatch($deliveryId)
                        ->onQueue('default')
                        ->afterCommit();
                }
            }

            return $notification;
        });
    }
    public function createWaitlistJoinedNotification(
        int $userId,
        int $eventId,
        ?int $occurrenceId,
        string $eventTitle,
        array $positions = []
    ): UserNotification {
        $posText = !empty($positions)
            ? implode(', ', array_map(fn($p) => position_name($p), $positions))
            : 'любое место';
    
        return $this->create(
            userId: $userId,
            type: 'waitlist_joined',
            title: 'Вы записаны в резерв',
            body: "ℹ️ Вы записаны в резерв на мероприятие «{$eventTitle}».\n"
                . "Ваша подписка на позиции: {$posText}.\n"
                . "🔔 Мы Вас уведомим, как позиция будет доступна!",
            payload: [
                'event_id'      => $eventId,
                'occurrence_id' => $occurrenceId,
                'event_title'   => $eventTitle,
                'positions'     => $positions,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }
    
    public function createWaitlistSpotFreedNotification(
        int $userId,
        int $eventId,
        ?int $occurrenceId,
        string $eventTitle,
        string $position = ''
    ): UserNotification {
        $posLabel = $position ? position_name($position) : 'место';
    
        return $this->create(
            userId: $userId,
            type: 'waitlist_spot_freed',
            title: 'Освободилось место на мероприятии',
            body: "🔥❗️ На мероприятие «{$eventTitle}» освободилось место: {$posLabel}.\n"
                . "Запишитесь, пока оно свободно! У вас есть 15 минут.",
            payload: [
                'event_id'      => $eventId,
                'occurrence_id' => $occurrenceId,
                'event_title'   => $eventTitle,
                'position'      => $position,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }
    public function createGroupInviteNotification(
        int $toUserId,
        int $fromUserId,
        int $eventId,
        int $inviteId,
        string $groupKey,
        bool $autoJoinAfterRegistration = false
    ): UserNotification {
        $fromUser  = User::query()->find($fromUserId);
        $fromLabel = $fromUser?->name ?: $fromUser?->email ?: ('#' . $fromUserId);

        $title = 'Приглашение в группу';
        $body  = $autoJoinAfterRegistration
            ? "Пользователь {$fromLabel} пригласил вас в пару на мероприятие. Сначала запишитесь на мероприятие, потом примите приглашение."
            : "Пользователь {$fromLabel} пригласил вас в пару на мероприятие.";

        return $this->create(
            userId: $toUserId,
            type: 'group_invite',
            title: $title,
            body: $body,
            payload: [
                'event_id'                   => $eventId,
                'invite_id'                  => $inviteId,
                'group_key'                  => $groupKey,
                'from_user_id'               => $fromUserId,
                'from_user_name'             => $fromLabel,
                'auto_join_after_registration' => $autoJoinAfterRegistration,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }

    public function createTournamentTeamInviteNotification(
        int $toUserId,
        int $fromUserId,
        int $eventId,
        int $teamId,
        int $inviteId,
        string $teamName,
        string $eventTitle,
        string $inviteUrl,
        string $teamRole,
        ?string $positionCode = null
    ): UserNotification {
        $fromUser = User::query()->find($fromUserId);

        $fromLabel = $fromUser?->name
            ?: $fromUser?->email
            ?: ('#' . $fromUserId);

        $roleLabel = match ($teamRole) {
            'player'  => 'основной игрок',
            'reserve' => 'запасной',
            default   => $teamRole,
        };

        $positionLabel = match ($positionCode) {
            'setter'   => 'связующий',
            'outside'  => 'доигровщик',
            'opposite' => 'диагональный',
            'middle'   => 'центральный блокирующий',
            'libero'   => 'либеро',
            default    => null,
        };

        $body = $positionLabel
            ? "Капитан {$fromLabel} приглашает вас в команду «{$teamName}» на турнир «{$eventTitle}». Роль: {$roleLabel}, позиция: {$positionLabel}."
            : "Капитан {$fromLabel} приглашает вас в команду «{$teamName}» на турнир «{$eventTitle}». Роль: {$roleLabel}.";

        return $this->create(
            userId: $toUserId,
            type: 'tournament_team_invite',
            title: 'Приглашение в команду',
            body: $body,
            payload: [
                'event_id'      => $eventId,
                'team_id'       => $teamId,
                'invite_id'     => $inviteId,
                'invite_url'    => $inviteUrl,
                'team_name'     => $teamName,
                'event_title'   => $eventTitle,
                'team_role'     => $teamRole,
                'position_code' => $positionCode,
                'from_user_id'  => $fromUserId,
                'from_user_name'=> $fromLabel,
                'button_text'   => 'Открыть приглашение',
                'button_url'    => $inviteUrl,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }

    public function createRegistrationCreatedNotification(
        int $userId,
        int $eventId,
        ?int $occurrenceId,
        string $eventTitle
    ): UserNotification {
        return $this->create(
            userId: $userId,
            type: 'registration_created',
            title: 'Вы записаны на мероприятие',
            body: "Вы успешно записались на мероприятие «{$eventTitle}».",
            payload: [
                'event_id'      => $eventId,
                'occurrence_id' => $occurrenceId,
                'event_title'   => $eventTitle,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }

    public function createRegistrationCancelledNotification(
        int $userId,
        int $eventId,
        ?int $occurrenceId,
        string $eventTitle
    ): UserNotification {
        return $this->create(
            userId: $userId,
            type: 'registration_cancelled',
            title: 'Запись на мероприятие отменена',
            body: "Вы отменили запись на мероприятие «{$eventTitle}».",
            payload: [
                'event_id'      => $eventId,
                'occurrence_id' => $occurrenceId,
                'event_title'   => $eventTitle,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }

    public function createRegistrationCancelledByOrganizerNotification(
        int $userId,
        int $eventId,
        ?int $occurrenceId,
        string $eventTitle,
        ?int $cancelledByUserId = null
    ): UserNotification {
        $cancelledByUser = $cancelledByUserId ? User::query()->find($cancelledByUserId) : null;

        return $this->create(
            userId: $userId,
            type: 'registration_cancelled_by_organizer',
            title: 'Ваша запись была отменена',
            body: "Организатор отменил вашу запись на мероприятие «{$eventTitle}».",
            payload: [
                'event_id'               => $eventId,
                'occurrence_id'          => $occurrenceId,
                'event_title'            => $eventTitle,
                'cancelled_by_user_id'   => $cancelledByUserId,
                'organizer_name'         => $cancelledByUser?->name,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }

    public function createEventReminderNotification(
        int $userId,
        int $eventId,
        ?int $occurrenceId,
        string $eventTitle,
        ?string $startsAtText = null
    ): UserNotification {
        $body = $startsAtText
            ? "Напоминание: мероприятие «{$eventTitle}» скоро начнётся ({$startsAtText})."
            : "Напоминание: мероприятие «{$eventTitle}» скоро начнётся.";

        return $this->create(
            userId: $userId,
            type: 'event_reminder',
            title: 'Скоро начало мероприятия',
            body: $body,
            payload: [
                'event_id'       => $eventId,
                'occurrence_id'  => $occurrenceId,
                'event_title'    => $eventTitle,
                'starts_at_text' => $startsAtText,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }

    public function createEventCancelledNotification(
        int $userId,
        int $eventId,
        ?int $occurrenceId,
        string $eventTitle,
        ?string $reason = null
    ): UserNotification {
        $body = $reason
            ? "Мероприятие «{$eventTitle}» отменено. Причина: {$reason}."
            : "Мероприятие «{$eventTitle}» отменено организатором.";

        return $this->create(
            userId: $userId,
            type: 'event_cancelled',
            title: 'Мероприятие отменено',
            body: $body,
            payload: [
                'event_id'      => $eventId,
                'occurrence_id' => $occurrenceId,
                'event_title'   => $eventTitle,
                'cancel_reason' => $reason,
                'reason'        => $reason,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }

    public function createEventCancelledByQuorumNotification(
        int $userId,
        int $eventId,
        ?int $occurrenceId,
        string $eventTitle,
        ?string $startsAtText = null,
        ?string $locationText = null,
        ?string $userName = null
    ): UserNotification {
        $userGreeting = $userName ? "Уважаем(ая)ый, {$userName}! " : '';
        $dateStr  = $startsAtText ? "📅 {$startsAtText}" : '';
        $locStr   = $locationText ? "📍 {$locationText}" : '';
        $body = "⚠️ {$userGreeting}Приносим свои извинения, но в связи с отсутствием кворума, мероприятие ℹ️ «{$eventTitle}»";
        if ($dateStr) $body .= " {$dateStr}";
        if ($locStr)  $body .= " {$locStr}";
        $body .= " не состоится! 💔 Будем Вас ждать в следующий раз!";

        return $this->create(
            userId: $userId,
            type: 'event_cancelled_quorum',
            title: 'Мероприятие отменено',
            body: $body,
            payload: [
                'event_id'      => $eventId,
                'occurrence_id' => $occurrenceId,
                'event_title'   => $eventTitle,
                'cancel_reason' => 'quorum_not_reached',
                'reason'        => 'quorum_not_reached',
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }

    public function createEventInviteNotification(
        int $toUserId,
        int $fromUserId,
        int $eventId,
        int $occurrenceId,
        string $eventTitle,
        string $eventUrl
    ): UserNotification {
        $fromUser  = User::query()->find($fromUserId);
        $fromName  = trim(($fromUser?->last_name ?? '') . ' ' . ($fromUser?->first_name ?? ''));
        if ($fromName === '') {
            $fromName = $fromUser?->name ?: $fromUser?->email ?: ('#' . $fromUserId);
        }

        return $this->create(
            userId: $toUserId,
            type: 'event_invite',
            title: "Приглашение на мероприятие «{$eventTitle}»",
            body: "Вас приглашает {$fromName} присоединиться к: {$eventTitle}.",
            payload: [
                'event_id'       => $eventId,
                'occurrence_id'  => $occurrenceId,
                'event_title'    => $eventTitle,
                'from_user_id'   => $fromUserId,
                'from_user_name' => $fromName,
                'button_text'    => 'Записаться',
                'button_url'     => $eventUrl,
            ],
            channels: ['in_app', 'telegram', 'vk', 'max']
        );
    }

    public function markAsRead(int $notificationId, int $userId): void
    {
        $notification = UserNotification::query()
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            throw new DomainException('Уведомление не найдено.');
        }

        if (!$notification->read_at) {
            $notification->read_at = now();
            $notification->save();
        }
    }

    public function markGroupInviteNotificationsAsRead(int $userId, int $inviteId): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->where('type', 'group_invite')
            ->whereRaw("(payload->>'invite_id')::int = ?", [$inviteId])
            ->whereNull('read_at')
            ->update([
                'read_at'    => now(),
                'updated_at' => now(),
            ]);
    }

    public function markAllAsRead(int $userId): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at'    => now(),
                'updated_at' => now(),
            ]);
    }

    public function unreadCount(int $userId): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function delete(int $notificationId, int $userId): void
    {
        $notification = UserNotification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            throw new DomainException('Уведомление не найдено.');
        }

        $notification->delete();
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    private function normalizeChannels(array $channels, User $user): array
    {
        $channels = collect($channels)
            ->map(fn ($v) => (string) $v)
            ->filter(fn ($v) => in_array($v, ['in_app', 'telegram', 'vk', 'max'], true))
            ->unique()
            ->values();

        $result = [];

        foreach ($channels as $channel) {
            if ($channel === 'telegram' && empty($user->telegram_id)) {
                continue;
            }
            // vk_notify_user_id — сохраняется при привязке VK-бота, а не vk_id (OAuth)
            if ($channel === 'vk' && empty($user->vk_notify_user_id)) {
                continue;
            }
            if ($channel === 'max' && empty($user->max_chat_id)) {
                continue;
            }
            $result[] = $channel;
        }

        if (!in_array('in_app', $result, true)) {
            array_unshift($result, 'in_app');
        }

        return array_values(array_unique($result));
    }

    private function makeDedupeKey(int $notificationId, string $channel, string $type): string
    {
        return 'user_notification:' . $notificationId . ':' . $channel . ':' . $type;
    }

    private function resolveRenderedContent(
        string $type,
        User $user,
        array $payload,
        string $fallbackTitle,
        ?string $fallbackBody
    ): array {
        $event = !empty($payload['event_id'])
            ? Event::query()->with(['location.city', 'organizer'])->find((int) $payload['event_id'])
            : null;

        $occurrence = !empty($payload['occurrence_id'])
            ? EventOccurrence::query()->with(['event.location.city', 'event.organizer'])->find((int) $payload['occurrence_id'])
            : null;

        $registration = $this->findRegistration(
            userId: (int) $user->id,
            eventId: !empty($payload['event_id']) ? (int) $payload['event_id'] : null,
            occurrenceId: !empty($payload['occurrence_id']) ? (int) $payload['occurrence_id'] : null
        );

        $templateData = $this->templateDataBuilder->build(
            user: $user,
            event: $event,
            occurrence: $occurrence,
            registration: $registration,
            extra: $payload
        );

        $template = $this->templateService->findActiveTemplate($type, null);

        if (!$template) {
            return [
                'title'         => $this->templateRenderer->render($fallbackTitle, $templateData) ?? $fallbackTitle,
                'body'          => $this->templateRenderer->render($fallbackBody, $templateData),
                'image_url'     => $payload['image_url'] ?? null,
                'button_text'   => $payload['button_text'] ?? null,
                'button_url'    => $payload['button_url'] ?? null,
                'template_data' => $templateData,  // ← структурированные данные для Sender-а
            ];
        }

        return [
            'title'         => $this->templateRenderer->render($template->title_template ?: $fallbackTitle, $templateData) ?? $fallbackTitle,
            'body'          => $this->templateRenderer->render($template->body_template ?: $fallbackBody, $templateData),
            'image_url'     => $this->templateRenderer->render($template->image_url ?? ($payload['image_url'] ?? null), $templateData),
            'button_text'   => $this->templateRenderer->render($template->button_text ?? ($payload['button_text'] ?? null), $templateData),
            'button_url'    => $this->templateRenderer->render($template->button_url_template ?? ($payload['button_url'] ?? null), $templateData),
            'template_data' => $templateData,  // ← структурированные данные для Sender-а
        ];
    }

    private function findRegistration(int $userId, ?int $eventId = null, ?int $occurrenceId = null): ?EventRegistration
    {
        $q = EventRegistration::query()->where('user_id', $userId);

        if ($eventId) {
            $q->where('event_id', $eventId);
        }

        if ($occurrenceId) {
            $q->where('occurrence_id', $occurrenceId);
        }

        return $q->latest('id')->first();
    }

    public function createUserLevelVotedNotification(int $userId, string $profileUrl): void
    {
        try {
            $this->create(
                userId: $userId,
                type: 'user_level_voted',
                title: 'Вам поставили оценку уровня!',
                body: null,
                payload: ['profile_url' => $profileUrl],
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        } catch (\Throwable $e) {
            \Log::warning('user_level_voted notification failed: ' . $e->getMessage());
        }
    }

    public function createUserPlayLikedNotification(int $userId, string $profileUrl): void
    {
        try {
            $this->create(
                userId: $userId,
                type: 'user_play_liked',
                title: 'Кому-то нравится с вами играть!',
                body: null,
                payload: ['profile_url' => $profileUrl],
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        } catch (\Throwable $e) {
            \Log::warning('user_play_liked notification failed: ' . $e->getMessage());
        }
    }
}
