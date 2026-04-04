<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BroadcastService
{
    public function __construct(
        private UserNotificationService $userNotificationService
    ) {}

    public function countUsers(array $filters): int
    {
        return $this->resolveUsersQuery($filters)->count();
    }

    public function dryRun(array $filters, array $channels = []): array
    {
        $channels = !empty($channels) ? array_values($channels) : ['in_app'];
    
        $limit = 10;

        $users = $this->resolveUsersQuery($filters)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    
        $items = [];
        $stats = [
            'in_app' => 0,
            'telegram' => 0,
            'vk' => 0,
            'max' => 0,
            'no_external_channels' => 0,
        ];
    
        foreach ($users as $user) {
            $availableChannels = $this->resolveAvailableChannelsForUser($user, $channels);
    
            foreach ($availableChannels['channels'] as $channel) {
                if (array_key_exists($channel, $stats)) {
                    $stats[$channel]++;
                }
            }
    
            $external = array_intersect($availableChannels['channels'], ['telegram', 'vk', 'max']);
            if (count($external) === 0) {
                $stats['no_external_channels']++;
            }
    
            $items[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'telegram_id' => $user->telegram_id,
                'vk_id' => $user->vk_id,
                'max_chat_id' => $user->max_chat_id,
                'channels' => $availableChannels['channels'],
                'skipped' => $availableChannels['skipped'],
            ];
        }
    
        return [
            'total' => $this->resolveUsersQuery($filters)->count(),
            'preview_count' => count($items),
            'limit' => $limit,
            'items' => $items,
        ];
    }

    public function launch(int $broadcastId): int
    {
        $broadcast = DB::table('broadcasts')->where('id', $broadcastId)->first();

        if (!$broadcast) {
            abort(404);
        }
        if (in_array((string) $broadcast->status, ['processing', 'sent'], true)) {
            return 0;
        }
        $filters = $this->decodeJsonObject($broadcast->filters_json);
        $channels = $this->decodeJsonArray($broadcast->channels_json);
        $channels = !empty($channels) ? $channels : ['in_app'];

        $users = $this->resolveUsers($filters);

        DB::table('broadcasts')
            ->where('id', $broadcastId)
            ->update([
                'status' => 'processing',
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        $count = 0;

        foreach ($users as $user) {
            $notification = $this->userNotificationService->create(
                userId: (int) $user->id,
                type: 'admin_broadcast',
                title: (string) ($broadcast->title ?: $broadcast->name),
                body: $broadcast->body,
                payload: [
                    'broadcast_id' => (int) $broadcast->id,
                    'image_url' => $broadcast->image_url,
                    'button_text' => $broadcast->button_text,
                    'button_url' => $broadcast->button_url,
                    'format' => 'plain',
                ],
                channels: $channels
            );

            DB::table('broadcast_recipients')->updateOrInsert(
                [
                    'broadcast_id' => (int) $broadcast->id,
                    'user_id' => (int) $user->id,
                ],
                [
                    'user_notification_id' => (int) $notification->id,
                    'status' => 'created',
                    'error' => null,
                    'meta' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $count++;
        }

        DB::table('broadcasts')
            ->where('id', $broadcastId)
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
                'updated_at' => now(),
            ]);

        return $count;
    }

    private function resolveAvailableChannelsForUser(User $user, array $channels): array
    {
        $channels = !empty($channels) ? $channels : ['in_app'];

        $ok = [];
        $skipped = [];

        foreach ($channels as $channel) {
            if ($channel === 'in_app') {
                $ok[] = 'in_app';
                continue;
            }

            if ($channel === 'telegram') {
                if (!empty($user->telegram_id)) {
                    $ok[] = 'telegram';
                } else {
                    $skipped[] = 'telegram: no telegram_id';
                }
                continue;
            }

            if ($channel === 'vk') {
                if (!empty($user->vk_id)) {
                    $ok[] = 'vk';
                } else {
                    $skipped[] = 'vk: no vk_id';
                }
                continue;
            }

            if ($channel === 'max') {
                if (!empty($user->max_chat_id)) {
                    $ok[] = 'max';
                } else {
                    $skipped[] = 'max: no max_chat_id';
                }
                continue;
            }
        }

        return [
            'channels' => array_values(array_unique($ok)),
            'skipped' => array_values(array_unique($skipped)),
        ];
    }

    private function resolveUsers(array $filters): Collection
    {
        return $this->resolveUsersQuery($filters)
            ->orderBy('id')
            ->get();
    }

    private function resolveUsersQuery(array $filters): Builder
    {
        $q = User::query();

        if (!empty($filters['has_telegram'])) {
            $q->whereNotNull('telegram_id')
                ->where('telegram_id', '!=', '');
        }

        if (!empty($filters['has_vk'])) {
            $q->whereNotNull('vk_id')
                ->where('vk_id', '!=', '');
        }

        if (!empty($filters['has_max'])) {
            $q->whereNotNull('max_chat_id')
                ->where('max_chat_id', '!=', '');
        }

        if (!empty($filters['city']) && Schema::hasColumn('users', 'city')) {
            $q->where('city', 'ilike', '%' . $filters['city'] . '%');
        }

        return $q;
    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }

        return [];
    }

    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}