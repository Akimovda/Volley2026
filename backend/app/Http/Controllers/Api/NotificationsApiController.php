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

        $query = $request->user()
            ->notificationsInbox()
            ->orderByDesc('created_at');

        $total = $query->count();
        $items = (clone $query)->forPage($page, $perPage)->get();

        $data = $items->map(fn (UserNotification $n) => $this->format($n));

        return response()->json([
            'data'      => $data,
            'has_more'  => ($page * $perPage) < $total,
            'next_page' => $page + 1,
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
        $request->user()->notificationsInbox()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
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

        // Оставляем только path (без домена) для универсальности
        $url = null;
        if ($rawUrl) {
            $parsed = parse_url((string) $rawUrl);
            $url = ($parsed['path'] ?? '/');
            if (!empty($parsed['query'])) {
                $url .= '?' . $parsed['query'];
            }
        }

        return [
            'id'               => $n->id,
            'type'             => $n->type,
            'title'            => $n->title,
            'body'             => $n->body,
            'url'              => $url,
            'read_at'          => $n->read_at?->toIso8601String(),
            'created_at'       => $n->created_at->toIso8601String(),
            'created_at_human' => $n->created_at->diffForHumans(),
        ];
    }
}
