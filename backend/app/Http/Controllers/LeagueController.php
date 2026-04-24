<?php

namespace App\Http\Controllers;

use App\Models\League;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;

class LeagueController extends Controller
{
    /* ================================================================
     *  Список лиг организатора
     * ================================================================ */

    public function index(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['organizer', 'admin'])) {
            abort(403, 'Доступно только организаторам.');
        }

        if ($user->isAdmin()) {
            $leagues = League::with('seasons', 'organizer')
                ->orderByDesc('created_at')->get();
        } else {
            $leagues = League::with('seasons')
                ->where('organizer_id', $user->id)
                ->orderByDesc('created_at')->get();
        }

        return view('leagues.index', compact('leagues'));
    }

    /* ================================================================
     *  Создание лиги — форма
     * ================================================================ */

    public function create(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['organizer', 'admin'])) {
            abort(403, 'Доступно только организаторам.');
        }

        $organizers = collect();
        if ($user->isAdmin()) {
            $organizers = User::whereIn('role', ['organizer', 'admin'])
                ->orderBy('first_name')->orderBy('last_name')
                ->get();
        }

        return view('leagues.create', compact('organizers'));
    }

    /* ================================================================
     *  Создание лиги — сохранение
     * ================================================================ */

    public function store(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['organizer', 'admin'])) {
            abort(403, 'Доступно только организаторам.');
        }

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'direction'      => 'required|in:classic,beach',
            'description'    => 'nullable|string|max:2000',
            'organizer_id'   => 'nullable|exists:users,id',
            'vk'             => 'nullable|string|max:255',
            'telegram'       => 'nullable|string|max:255',
            'max_messenger'  => 'nullable|string|max:255',
            'website'        => 'nullable|url|max:255',
            'phone'          => 'nullable|string|max:30',
            'logo'           => 'nullable|image|max:2048',
        ]);

        $organizerId = $user->id;
        if ($user->isAdmin() && !empty($validated['organizer_id'])) {
            $organizerId = $validated['organizer_id'];
        }

        $league = League::create([
            'organizer_id'  => $organizerId,
            'name'          => $validated['name'],
            'slug'          => $this->uniqueSlug($validated['name']),
            'direction'     => $validated['direction'],
            'description'   => $validated['description'] ?? null,
            'vk'            => $validated['vk'] ?? null,
            'telegram'      => $validated['telegram'] ?? null,
            'max_messenger' => $validated['max_messenger'] ?? null,
            'website'       => $validated['website'] ?? null,
            'phone'         => $validated['phone'] ?? null,
            'status'        => League::STATUS_ACTIVE,
        ]);

        if ($request->hasFile('logo')) {
            $league->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return redirect()
            ->route('leagues.edit', $league)
            ->with('success', 'Лига создана. Добавьте первый сезон.');
    }

    /* ================================================================
     *  Редактирование лиги
     * ================================================================ */

    public function edit(Request $request, League $league)
    {
        $this->authorizeLeague($request, $league);

        $league->load([
            'seasons' => fn($q) => $q->orderByDesc('starts_at'),
            'seasons.leagues',
            'seasons.seasonEvents.event',
        ]);

        return view('leagues.edit', compact('league'));
    }

    /* ================================================================
     *  Обновление лиги
     * ================================================================ */

    public function update(Request $request, League $league)
    {
        $this->authorizeLeague($request, $league);

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'direction'      => 'required|in:classic,beach',
            'description'    => 'nullable|string|max:2000',
            'vk'             => 'nullable|string|max:255',
            'telegram'       => 'nullable|string|max:255',
            'max_messenger'  => 'nullable|string|max:255',
            'website'        => 'nullable|url|max:255',
            'phone'          => 'nullable|string|max:30',
            'status'         => 'nullable|in:active,archived',
            'logo'           => 'nullable|image|max:2048',
            'remove_logo'    => 'nullable|boolean',
        ]);

        $league->update(collect($validated)->except(['logo', 'remove_logo'])->toArray());

        if ($request->boolean('remove_logo')) {
            $league->clearMediaCollection('logo');
        } elseif ($request->hasFile('logo')) {
            $league->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return back()->with('success', 'Лига обновлена.');
    }

    /* ================================================================
     *  Публичный каталог всех лиг
     * ================================================================ */

    public function publicIndex()
    {
        $leagues = League::with(['organizer', 'seasons' => fn($q) => $q->where('status', 'active')])
            ->where('status', League::STATUS_ACTIVE)
            ->orderByDesc('created_at')
            ->get();

        return view('leagues.public', compact('leagues'));
    }

    /* ================================================================
     *  Публичная страница лиги
     * ================================================================ */

    public function show(League $league)
    {
        $league->load([
            'organizer',
            'seasons' => fn($q) => $q->orderByDesc('starts_at'),
            'seasons.seasonEvents.event',
            'seasons.stats' => fn($q) => $q->orderByDesc('match_win_rate'),
        ]);

        return view('leagues.show', compact('league'));
    }

    public function showBySlug(string $slug)
    {
        $league = League::where('slug', $slug)->firstOrFail();
        return $this->show($league);
    }

    /* ================================================================
     *  Auth helper
     * ================================================================ */

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'league';
        }

        if (!League::where('slug', $slug)->exists()) {
            return $slug;
        }

        $i = 2;
        while (League::where('slug', $slug . '-' . $i)->exists()) {
            $i++;
        }

        return $slug . '-' . $i;
    }

    public function destroy(Request $request, League $league)
    {
        $this->authorizeLeague($request, $league);

        if ($league->seasons()->where('status', 'active')->exists()) {
            return back()->with('error', 'Нельзя удалить лигу с активными сезонами. Сначала завершите их.');
        }

        $league->clearMediaCollection('logo');
        $league->delete();

        return redirect()->route('leagues.index')->with('success', 'Лига удалена.');
    }

    private function authorizeLeague(Request $request, League $league): void
    {
        $user = $request->user();
        if ($league->organizer_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Вы не являетесь владельцем этой лиги.');
        }
    }
}
