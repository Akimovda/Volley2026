<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CountryRules;
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

        // Правила ФИО/телефона — по стране организатора (RU/KZ/UZ — как раньше: кириллица и +7)
        $country = CountryRules::normalizeCountry($actor->country_code);
        $cyr     = CountryRules::usesCyrillicNames($country);

        $request->merge([
            'phone'      => CountryRules::normalizePhone($request->input('phone'), $country),
            'last_name'  => $cyr ? $request->input('last_name')  : CountryRules::normalizeLatinName($request->input('last_name')),
            'first_name' => $cyr ? $request->input('first_name') : CountryRules::normalizeLatinName($request->input('first_name')),
        ]);

        $data = $request->validate([
            'last_name'  => ['required', 'string', 'min:2', 'max:255', 'regex:' . CountryRules::nameRegex($country)],
            'first_name' => ['required', 'string', 'min:2', 'max:255', 'regex:' . CountryRules::nameRegex($country)],
            'gender'     => ['required', 'in:m,f'],
            'phone'      => ['nullable', 'string', function ($attr, $value, $fail) use ($country) {
                if (!CountryRules::isValidPhone($value, $country)) {
                    $fail(CountryRules::phoneMessage($country));
                }
            }],
        ], [
            'last_name.regex'  => $cyr ? __('placeholders.err_cyr') : CountryRules::nameMessage($country, 'last'),
            'first_name.regex' => $cyr ? __('placeholders.err_cyr') : CountryRules::nameMessage($country, 'first'),
        ]);
        $data['country_code'] = $country;

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

}
