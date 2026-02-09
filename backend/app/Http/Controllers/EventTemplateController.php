<?php

namespace App\Http\Controllers;

use App\Models\EventTemplate;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EventTemplateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $ownerCol = $this->ownerColumn();

        $templates = EventTemplate::query()
            ->where($ownerCol, (int)$user->id)
            ->orderByDesc('id')
            ->paginate(20);

        // ✅ Собираем location_id из payload (без N+1) + поддержка json string
        $locationIds = [];
        foreach ($templates as $tpl) {
            $payload = $this->payloadToArray($tpl->payload);
            $locId = $payload['location_id'] ?? null;
            if (is_numeric($locId)) $locationIds[] = (int)$locId;
        }

        $locationIds = array_values(array_unique(array_filter($locationIds)));
        $locationsById = collect();

        if (!empty($locationIds)) {
            $locationsById = Location::query()
                ->whereIn('id', $locationIds)
                ->get(['id', 'name', 'city', 'address'])
                ->keyBy('id');
        }

        return view('event_templates.index', compact('templates', 'locationsById'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        return view('event_templates.form', [
            'template' => null,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'payload_text' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $payload = $this->extractPayloadFromRequest($request, $data);
        $payload = $this->sanitizePayload($payload);

        $tpl = new EventTemplate();
        $ownerCol = $this->ownerColumn();
        $tpl->{$ownerCol} = (int)$user->id;

        if (Schema::hasColumn('event_templates', 'user_id')) {
            $tpl->user_id = (int)$user->id;
        }

        $tpl->name = $data['name'];
        $tpl->payload = $payload;
        $tpl->save();

        return redirect()
            ->route('event_templates.index')
            ->with('status', 'Шаблон создан ✅');
    }

    public function edit(Request $request, EventTemplate $eventTemplate)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->assertOwned($eventTemplate, (int)$user->id);

        return view('event_templates.form', [
            'template' => $eventTemplate,
        ]);
    }

    public function update(Request $request, EventTemplate $eventTemplate)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->assertOwned($eventTemplate, (int)$user->id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'payload_text' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $payload = $this->extractPayloadFromRequest($request, $data);
        $payload = $this->sanitizePayload($payload);

        $eventTemplate->name = $data['name'];
        $eventTemplate->payload = $payload;
        $eventTemplate->save();

        return redirect()
            ->route('event_templates.index')
            ->with('status', 'Шаблон обновлён ✅');
    }

    public function destroy(Request $request, EventTemplate $eventTemplate)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->assertOwned($eventTemplate, (int)$user->id);

        $eventTemplate->delete();

        return redirect()
            ->route('event_templates.index')
            ->with('status', 'Шаблон удалён ✅');
    }

    public function apply(Request $request, EventTemplate $eventTemplate)
    {
logger()->info('TPL APPLY HIT', ['tpl' => $eventTemplate->id, 'user' => Auth::id()]);
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        // ✅ единая проверка владельца
        $this->assertOwned($eventTemplate, (int)$user->id);

        $payload = $this->payloadToArray($eventTemplate->payload);

        // ✅ Нормализация game_gender_limited_positions (для old() чекбоксов)
        if (array_key_exists('game_gender_limited_positions', $payload)) {
            $v = $payload['game_gender_limited_positions'];
            if (is_string($v)) $v = [$v];
            if (!is_array($v)) $v = [];
            $payload['game_gender_limited_positions'] = array_values(array_unique(array_map('strval', $v)));
        }

        // ✅ Не даём не-админу протащить organizer_id из шаблона
        $role = (string)($user->role ?? 'user');
        if ($role !== 'admin') {
            unset($payload['organizer_id']);
        }

        // ✅ allow_registration=0 => recurring сбросить (как в EventCreateController@store)
        if (isset($payload['allow_registration']) && (int)$payload['allow_registration'] === 0) {
            $payload['is_recurring'] = 0;
            $payload['recurrence_rule'] = '';
        }

        return redirect()
            ->route('events.create')
            ->withInput($payload)
            ->with('status', 'Шаблон применён ✅');
    }

    private function assertOwned(EventTemplate $tpl, int $userId): void
    {
        $ownerCol = $this->ownerColumn();
        $ownerId = (int)($tpl->{$ownerCol} ?? 0);

        // у тебя было 404 — это ок (не палим существование чужих шаблонов)
        abort_unless($ownerId === (int)$userId, 404);
    }

    private function ownerColumn(): string
    {
        return Schema::hasColumn('event_templates', 'owner_user_id')
            ? 'owner_user_id'
            : 'user_id';
    }

    private function payloadToArray($payload): array
    {
        if (is_array($payload)) return $payload;

        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) return $decoded;
        }

        return [];
    }

    private function extractPayloadFromRequest(Request $request, array $validated): array
    {
        $payloadText = $validated['payload_text'] ?? null;

        if (is_string($payloadText) && trim($payloadText) !== '') {
            $decoded = json_decode($payloadText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'payload_text' => 'JSON некорректен: ' . json_last_error_msg(),
                ]);
            }

            if (!is_array($decoded)) {
                throw ValidationException::withMessages([
                    'payload_text' => 'JSON должен быть объектом/массивом (на верхнем уровне).',
                ]);
            }

            return $decoded;
        }

        $payload = $validated['payload'] ?? [];
        return is_array($payload) ? $payload : [];
    }

    private function sanitizePayload(array $payload): array
    {
        unset($payload['_token'], $payload['_method']);

        // убираем пустые ключи
        $payload = Arr::where($payload, fn($v, $k) => $k !== null && $k !== '');

        // ✅ важная нормализация для чекбоксов
        if (array_key_exists('game_gender_limited_positions', $payload)) {
            $v = $payload['game_gender_limited_positions'];
            if (is_string($v)) $v = [$v];
            if (!is_array($v)) $v = [];
            $payload['game_gender_limited_positions'] = array_values(array_unique(array_map('strval', $v)));
        }

        return $payload;
    }
}
