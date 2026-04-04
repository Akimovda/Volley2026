<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BroadcastService;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminBroadcastController extends Controller
{
    public function index(): View
    {
        $broadcasts = DB::table('broadcasts')
            ->leftJoin('users', 'users.id', '=', 'broadcasts.created_by')
            ->select([
                'broadcasts.*',
                'users.name as created_by_name',
            ])
            ->orderByDesc('broadcasts.id')
            ->paginate(20);

        return view('admin.broadcasts.index', [
            'broadcasts' => $broadcasts,
        ]);
    }

    public function create(): View
    {
        return view('admin.broadcasts.create', [
            'broadcast' => null,
            'channelOptions' => $this->channelOptions(),
            'statusOptions' => $this->statusOptions(),
            'channels' => ['in_app'],
            'filters' => [],
        ]);
    }

    public function store(Request $request, BroadcastService $broadcastService): RedirectResponse
    {
        $data = $this->validateBroadcast($request);
    
        $id = (int) DB::table('broadcasts')->insertGetId([
            'created_by' => (int) $request->user()->id,
            'name' => $data['name'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'button_text' => $data['button_text'] ?? null,
            'button_url' => $data['button_url'] ?? null,
            'filters_json' => json_encode($this->buildFiltersJson($data), JSON_UNESCAPED_UNICODE),
            'channels_json' => json_encode(array_values($data['channels'] ?? ['in_app']), JSON_UNESCAPED_UNICODE),
            'status' => $data['status'] ?? 'draft',
            'scheduled_at' => !empty($data['scheduled_at']) ? $data['scheduled_at'] : null,
            'started_at' => null,
            'sent_at' => null,
            'meta' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    
        if ($request->input('action') === 'save_and_launch') {
            $count = $broadcastService->launch($id);
    
            return redirect()
                ->route('admin.broadcasts.edit', $id)
                ->with('status', "Рассылка сохранена и запущена ✅ Получателей: {$count}");
        }
    
        return redirect()
            ->route('admin.broadcasts.edit', $id)
            ->with('status', 'Рассылка создана ✅');
    }

    public function edit(int $broadcast): View
    {
        $row = DB::table('broadcasts')->where('id', $broadcast)->first();
        abort_unless($row, 404);

        return view('admin.broadcasts.edit', [
            'broadcast' => $row,
            'channelOptions' => $this->channelOptions(),
            'statusOptions' => $this->statusOptions(),
            'filters' => $this->decodeJsonObject($row->filters_json),
            'channels' => $this->decodeJsonArray($row->channels_json),
        ]);
    }

    public function update(Request $request, int $broadcast, BroadcastService $broadcastService): RedirectResponse
    {
        $row = DB::table('broadcasts')->where('id', $broadcast)->first();
        abort_unless($row, 404);
    
        $data = $this->validateBroadcast($request);
    
        DB::table('broadcasts')
            ->where('id', $broadcast)
            ->update([
                'name' => $data['name'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'image_url' => $data['image_url'] ?? null,
                'button_text' => $data['button_text'] ?? null,
                'button_url' => $data['button_url'] ?? null,
                'filters_json' => json_encode($this->buildFiltersJson($data), JSON_UNESCAPED_UNICODE),
                'channels_json' => json_encode(array_values($data['channels'] ?? ['in_app']), JSON_UNESCAPED_UNICODE),
                'status' => $data['status'] ?? 'draft',
                'scheduled_at' => !empty($data['scheduled_at']) ? $data['scheduled_at'] : null,
                'updated_at' => now(),
            ]);
    
        if ($request->input('action') === 'save_and_launch') {
            $count = $broadcastService->launch($broadcast);
    
            return redirect()
                ->route('admin.broadcasts.edit', $broadcast)
                ->with('status', "Рассылка сохранена и запущена ✅ Получателей: {$count}");
        }
    
        return redirect()
            ->route('admin.broadcasts.edit', $broadcast)
            ->with('status', 'Рассылка обновлена ✅');
    }

    public function launch(int $broadcast, BroadcastService $broadcastService): RedirectResponse
    {
        $row = DB::table('broadcasts')->where('id', $broadcast)->first();
        abort_unless($row, 404);

        $count = $broadcastService->launch($broadcast);

        return redirect()
            ->route('admin.broadcasts.edit', $broadcast)
            ->with('status', "Рассылка запущена ✅ Получателей: {$count}");
    }

    public function previewAudience(Request $request, BroadcastService $broadcastService): JsonResponse
    {
        $data = $request->validate([
            'filter_city' => ['nullable', 'string', 'max:255'],
            'filter_has_telegram' => ['nullable', 'boolean'],
            'filter_has_vk' => ['nullable', 'boolean'],
            'filter_has_max' => ['nullable', 'boolean'],
        ]);

        $filters = $this->buildFiltersJson($data);
        $count = $broadcastService->countUsers($filters);

        return response()->json([
            'ok' => true,
            'count' => $count,
        ]);
    }

    public function testSend(Request $request, UserNotificationService $userNotificationService): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'button_url' => ['nullable', 'string', 'max:2048'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:in_app,telegram,vk,max'],
        ]);

        $user = $request->user();
        abort_unless($user, 403);

        $notification = $userNotificationService->create(
            userId: (int) $user->id,
            type: 'admin_broadcast_test',
            title: (string) ($data['title'] ?? 'Тест рассылки'),
            body: $data['body'] ?? null,
            payload: [
                'image_url' => $data['image_url'] ?? null,
                'button_text' => $data['button_text'] ?? null,
                'button_url' => $data['button_url'] ?? null,
                'format' => 'plain',
                'is_test' => true,
            ],
            channels: $data['channels'] ?? ['in_app']
        );

        return response()->json([
            'ok' => true,
            'notification_id' => $notification->id,
            'message' => 'Тест отправлен',
        ]);
    }

   public function dryRun(Request $request, BroadcastService $broadcastService): JsonResponse
    {
        $data = $request->validate([
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:in_app,telegram,vk,max'],
            'filter_city' => ['nullable', 'string', 'max:255'],
            'filter_has_telegram' => ['nullable', 'boolean'],
            'filter_has_vk' => ['nullable', 'boolean'],
            'filter_has_max' => ['nullable', 'boolean'],
        ]);
    
        $filters = $this->buildFiltersJson($data);
        $channels = array_values($data['channels'] ?? ['in_app']);
    
        $result = $broadcastService->dryRun($filters, $channels);
    
        return response()->json([
            'ok' => true,
            'total' => $result['total'],
            'preview_count' => $result['preview_count'],
            'items' => $result['items'],
            'stats' => $result['stats'],
        ]);
    }

    private function validateBroadcast(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'button_url' => ['nullable', 'string', 'max:2048'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:in_app,telegram,vk,max'],
            'status' => ['required', 'string', 'in:draft,scheduled,processing,sent,failed,cancelled'],
            'scheduled_at' => ['nullable', 'date'],
            'filter_city' => ['nullable', 'string', 'max:255'],
            'filter_has_telegram' => ['nullable', 'boolean'],
            'filter_has_vk' => ['nullable', 'boolean'],
            'filter_has_max' => ['nullable', 'boolean'],
        ]);
    }

    private function buildFiltersJson(array $data): array
    {
        return array_filter([
            'city' => $data['filter_city'] ?? null,
            'has_telegram' => array_key_exists('filter_has_telegram', $data) ? (bool) $data['filter_has_telegram'] : null,
            'has_vk' => array_key_exists('filter_has_vk', $data) ? (bool) $data['filter_has_vk'] : null,
            'has_max' => array_key_exists('filter_has_max', $data) ? (bool) $data['filter_has_max'] : null,
        ], fn ($v) => $v !== null && $v !== '');
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

    private function channelOptions(): array
    {
        return [
            'in_app' => 'In-App',
            'telegram' => 'Telegram',
            'vk' => 'VK',
            'max' => 'MAX',
        ];
    }

    private function statusOptions(): array
    {
        return [
            'draft' => 'Черновик',
            'scheduled' => 'Запланирована',
            'processing' => 'Обрабатывается',
            'sent' => 'Отправлена',
            'failed' => 'Ошибка',
            'cancelled' => 'Отменена',
        ];
    }
}