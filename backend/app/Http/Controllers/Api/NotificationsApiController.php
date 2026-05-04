<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 50);
        $page    = max(1, (int) $request->get('page', 1));

        $inbox = $request->user()->notificationsInbox();

        $unreadCount = (clone $inbox)->whereNull('read_at')->count();

        $query = (clone $inbox)->orderByDesc('created_at');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $total = $query->count();
        $items = (clone $query)->forPage($page, $perPage)->get();

        return response()->json([
            'data'         => $items->map(fn (UserNotification $n) => $this->format($n)),
            'unread_count' => $unreadCount,
            'has_more'     => ($page * $perPage) < $total,
            'next_page'    => ($page * $perPage) < $total ? $page + 1 : null,
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $request->user()->notificationsInbox()->findOrFail($id)
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $request->user()->notificationsInbox()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true, 'count' => $count]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->notificationsInbox()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->notificationsInbox()->whereNull('read_at')->count();

        return response()->json(['count' => $count]);
    }

    private function format(UserNotification $n): array
    {
        $payload = $n->payload ?? [];
        $rawUrl  = $payload['button_url'] ?? $payload['event_url'] ?? $payload['url'] ?? null;

        $actionUrl = null;
        if ($rawUrl) {
            $parsed    = parse_url((string) $rawUrl);
            $actionUrl = $parsed['path'] ?? '/';
            if (!empty($parsed['query'])) {
                $actionUrl .= '?' . $parsed['query'];
            }
        }

        return [
            'id'               => $n->id,
            'type'             => $n->type,
            'title'            => $n->title,
            'body'             => $this->enrichBody($n->body, $payload, $actionUrl),
            'icon'             => $this->icon($n->type),
            'read'             => $n->read_at !== null,
            'read_at'          => $n->read_at?->toIso8601String(),
            'created_at'       => $n->created_at->toIso8601String(),
            'created_at_human' => $n->created_at->diffForHumans(),
            'action_url'       => $actionUrl,
        ];
    }

    private function enrichBody(?string $rawBody, array $payload, ?string $actionUrl): string
    {
        $body = $this->cleanBody($rawBody);

        $eventId = $payload['event_id'] ?? null;
        if (!$eventId && $actionUrl && preg_match('#/events/(\d+)#', $actionUrl, $m)) {
            $eventId = (int) $m[1];
        }

        if ($eventId) {
            $event = \App\Models\Event::with('location.city')->find($eventId);
            if ($event && $event->location) {
                $lines      = explode("\n", $body);
                $hasAddress = collect($lines)->contains(
                    fn (string $l) => str_contains($l, 'Адрес:') && strlen(trim(str_replace('Адрес:', '', $l))) > 1
                );

                if (!$hasAddress) {
                    $address = $event->location->name ?? '';
                    if ($event->location->address) {
                        $address .= ', ' . $event->location->address;
                    }
                    if ($event->location->city) {
                        $address .= ', ' . $event->location->city->name;
                    }
                    if ($address) {
                        $body .= "\nАдрес: " . $address;
                    }
                }
            }
        }

        return $body;
    }

    private function cleanBody(?string $body): string
    {
        if (!$body) return '';
        $lines = explode("\n", $body);
        $lines = array_filter($lines, function (string $line): bool {
            $line = trim($line);
            if ($line === '') return false;
            if (str_contains($line, 'http://') || str_contains($line, 'https://')) return false;
            if (str_contains($line, 'Открыть мероприятие')) return false;
            if (str_contains($line, 'Смотрите и делитесь')) return false;
            if (str_contains($line, 'Подробности')) return false;
            // Убираем строки-заглушки с пустым значением: "Адрес: ", "Место: " и т.д.
            if (preg_match('/^[^:]+:\s*$/', $line)) return false;
            return true;
        });
        return trim(implode("\n", $lines));
    }

    private function icon(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'event_reminder')       => 'calendar',
            str_starts_with($type, 'registration_created') => 'calendar',
            str_starts_with($type, 'friend_joined')        => 'team',
            str_starts_with($type, 'tournament')           => 'trophy',
            str_starts_with($type, 'league')               => 'trophy',
            str_starts_with($type, 'season')               => 'trophy',
            default                                        => 'bell',
        };
    }
}
