<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PlaceholderUserService;
use Illuminate\Http\Request;

/**
 * Раздел организатора/админа: аккаунты-«пустышки» без точек входа.
 */
class PlaceholderUserController extends Controller
{
    public function __construct(private PlaceholderUserService $service) {}

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->isAdmin() || $actor->isOrganizer()), 403);
        return $actor;
    }

    private function owned(Request $request, User $user): User
    {
        $actor = $this->actor($request);
        abort_unless($user->isPlaceholderManagedBy($actor), 404);
        return $user;
    }

    public function index(Request $request)
    {
        $actor = $this->actor($request);
        $items = $this->service->listFor($actor);

        return view('placeholders.index', [
            'items'    => $items,
            'isAdmin'  => $actor->isAdmin(),
            'history'  => $items->mapWithKeys(fn ($u) => [$u->id => $this->service->hasHistory($u)]),
        ]);
    }

    public function store(Request $request)
    {
        $actor = $this->actor($request);

        $request->merge([
            'phone' => $this->normalizePhone($request->input('phone')),
        ]);

        $data = $request->validate([
            'last_name'  => ['required', 'string', 'min:2', 'max:255', 'regex:/^[А-Яа-яЁё \-\'’]+$/u'],
            'first_name' => ['required', 'string', 'min:2', 'max:255', 'regex:/^[А-Яа-яЁё \-\'’]+$/u'],
            'gender'     => ['required', 'in:m,f'],
            'phone'      => ['nullable', 'string', 'regex:/^\+7\d{10}$/'],
        ], [
            'last_name.regex'  => __('placeholders.err_cyr'),
            'first_name.regex' => __('placeholders.err_cyr'),
            'phone.regex'      => __('placeholders.err_phone'),
        ]);

        $user = $this->service->create($actor, $data);

        // Остальные поля (уровни, дата рождения, город, амплуа) — на общей странице профиля
        return redirect()
            ->route('profile.complete', ['user_id' => $user->id])
            ->with('status', __('placeholders.created'));
    }

    public function destroy(Request $request, User $user)
    {
        $actor = $this->actor($request);
        $this->owned($request, $user);

        if ($this->service->hasHistory($user)) {
            return back()->with('error', __('placeholders.err_has_history'));
        }

        $this->service->delete($actor, $user);

        return redirect()->route('placeholders.index')->with('status', __('placeholders.deleted'));
    }

    public function mergeForm(Request $request, User $user)
    {
        $this->owned($request, $user);

        $q = trim((string) $request->query('q', ''));

        return view('placeholders.merge', [
            'placeholder' => $user,
            'suggested'   => $this->service->suggestMatches($user),
            'found'       => $q !== '' ? $this->service->searchRealUsers($q) : collect(),
            'q'           => $q,
            'hasHistory'  => $this->service->hasHistory($user),
            'phSummary'   => $this->service->summary($user),
            'service'     => $this->service,
        ]);
    }

    public function mergeDo(Request $request, User $user)
    {
        $actor = $this->actor($request);
        $this->owned($request, $user);

        $data = $request->validate([
            'real_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $result = $this->service->mergeInto($actor, $user, User::findOrFail($data['real_user_id']));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', __('placeholders.err_merge'));
        }

        $msg = __('placeholders.merged', ['n' => (int) ($result['transferred'] ?? 0)]);
        if (!empty($result['cancelled_conflicts'])) {
            $titles = collect($result['cancelled_conflicts'])
                ->map(fn ($c) => "«{$c['title']}» ({$c['starts_at']})")->implode(', ');
            $msg .= ' ' . __('placeholders.merged_conflicts', ['list' => $titles]);
        }

        return redirect()->route('placeholders.index')->with('status', $msg);
    }

    private function normalizePhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true)) {
            $digits = substr($digits, 1);
        }
        return '+7' . $digits;
    }
}
