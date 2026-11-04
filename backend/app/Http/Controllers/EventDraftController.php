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
    private const SKIP = ['_token', '_method', 'wizard_step', 'from_event_id', 'draft_id'];

    /** Сколько черновиков хранится у одного пользователя (старше — удаляются). */
    public const MAX_DRAFTS = 10;

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

        // draft_id — черновик, с которым работает форма; чужой/несуществующий id → создаём новый
        $draft = null;
        $draftId = (int) $request->input('draft_id', 0);
        if ($draftId > 0) {
            $draft = EventDraft::where('user_id', $user->id)->where('id', $draftId)->first();
        }
        if ($draft) {
            $draft->update(['step' => $step, 'payload' => $fields]);
        } else {
            $draft = EventDraft::create(['user_id' => $user->id, 'step' => $step, 'payload' => $fields]);
            $this->prune($user->id);
        }

        return response()->json(['ok' => true, 'saved' => true, 'draft_id' => $draft->id]);
    }

    /** Оставляем MAX_DRAFTS самых свежих черновиков пользователя. */
    private function prune(int $userId): void
    {
        $keep = EventDraft::where('user_id', $userId)->orderByDesc('updated_at')->orderByDesc('id')
            ->limit(self::MAX_DRAFTS)->pluck('id');
        EventDraft::where('user_id', $userId)->whereNotIn('id', $keep)->delete();
    }

    // GET /events/create/draft/restore/{draft?} — без id (старые ссылки) берём самый свежий
    public function restore(Request $request, ?int $draft = null): RedirectResponse
    {
        $user = $request->user();
        $this->access->ensureCanCreateEvents($user);

        $q = EventDraft::where('user_id', $user->id);
        $draft = $draft ? $q->where('id', $draft)->first() : $q->orderByDesc('updated_at')->first();
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
            ->with('draft_id', $draft->id)
            ->with('draft_restored', true);
    }

    // DELETE /events/create/draft/{draft?} — без id (старые страницы) удаляем самый свежий
    public function destroy(Request $request, ?int $draft = null): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $q = EventDraft::where('user_id', $user->id);
        if ($draft) {
            $q->where('id', $draft)->delete();
        } else {
            $latest = (clone $q)->orderByDesc('updated_at')->value('id');
            if ($latest) {
                EventDraft::where('id', $latest)->delete();
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('events.create');
    }
}
