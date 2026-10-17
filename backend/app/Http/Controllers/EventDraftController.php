<?php

namespace App\Http\Controllers;

use App\Models\EventDraft;
use App\Services\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Черновик мастера создания мероприятия (/events/create).
 * Автосохранение с формы, восстановление — через old()-механизм самой формы.
 */
class EventDraftController extends Controller
{
    /** Поля, которые в черновик не кладём. */
    private const SKIP = ['_token', '_method', 'wizard_step', 'from_event_id'];

    private const MAX_BYTES = 300000;

    public function __construct(private EventAccessService $access) {}

    // POST /events/create/draft
    public function save(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->access->ensureCanCreateEvents($user);

        $fields = $request->except(self::SKIP);
        // файлы в черновик не попадают (фото грузятся отдельно, в форме — только их ID)
        $fields = array_filter($fields, fn ($v) => !($v instanceof \Illuminate\Http\UploadedFile));

        // без названия и без выбранных настроек сохранять нечего
        if (trim((string) ($fields['title'] ?? '')) === '') {
            return response()->json(['ok' => true, 'saved' => false]);
        }

        if (strlen(json_encode($fields, JSON_UNESCAPED_UNICODE)) > self::MAX_BYTES) {
            return response()->json(['ok' => false, 'error' => 'too_large'], 413);
        }

        // wizard_step в форме — номер шага 1..3
        $step = max(1, min(3, (int) $request->input('wizard_step', 1)));

        EventDraft::updateOrCreate(
            ['user_id' => $user->id],
            ['step' => $step, 'payload' => $fields]
        );

        return response()->json(['ok' => true, 'saved' => true]);
    }

    // GET /events/create/draft/restore
    public function restore(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->access->ensureCanCreateEvents($user);

        $draft = EventDraft::where('user_id', $user->id)->first();
        if (!$draft) {
            return redirect()->route('events.create');
        }

        $payload = (array) $draft->payload;
        $query = [];
        if (!empty($payload['city_id'])) {
            // список локаций на сервере отдаётся по городу
            $query['city_id'] = (int) $payload['city_id'];
        }

        return redirect()
            ->route('events.create', $query)
            ->withInput($payload)
            ->with('wizard_initial_step', max(1, min(3, (int) $draft->step)))
            ->with('draft_restored', true);
    }

    // DELETE /events/create/draft
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        EventDraft::where('user_id', $user->id)->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('events.create');
    }
}
