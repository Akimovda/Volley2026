<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\UserTeam;
use App\Models\UserTeamMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserTeamController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        $teams = UserTeam::where('user_id', $user->id)
            ->with('members.user')
            ->orderByDesc('created_at')
            ->get();
        return view('user.teams.index', compact('teams'));
    }

    public function edit(Request $request, UserTeam $team)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        if ((int)$team->user_id !== (int)$user->id) abort(403);
        $team->load('members.user');
        $eventId = (int)$request->query('event_id', 0);
        $event = $eventId ? Event::with(['gameSettings', 'tournamentSetting'])->find($eventId) : null;
        $validationErrors = session('team_validation_errors', []);
        $teamSizeError = session('team_size_error');
        return view('user.teams.edit', compact('team', 'event', 'validationErrors', 'teamSizeError'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'direction' => ['required', 'string', 'in:classic,beach'],
            'subtype'   => ['nullable', 'string', 'max:16'],
            'members'   => ['nullable', 'array', 'max:30'],
            'members.*.user_id'       => ['required', 'integer', 'exists:users,id'],
            'members.*.role_code'     => ['nullable', 'string', 'in:captain,player,reserve'],
            'members.*.position_code' => ['nullable', 'string', 'in:setter,outside,opposite,middle,libero'],
        ]);

        DB::transaction(function () use ($user, $data) {
            $team = UserTeam::create([
                'user_id'   => $user->id,
                'name'      => $data['name'],
                'direction' => $data['direction'],
                'subtype'   => $data['subtype'] ?? null,
            ]);

            // Captain = owner
            UserTeamMember::create([
                'user_team_id'  => $team->id,
                'user_id'       => $user->id,
                'role_code'     => 'captain',
                'position_code' => null,
            ]);

            foreach (($data['members'] ?? []) as $m) {
                if ((int)$m['user_id'] === (int)$user->id) continue; // skip captain duplicate
                UserTeamMember::firstOrCreate(
                    ['user_team_id' => $team->id, 'user_id' => (int)$m['user_id']],
                    [
                        'role_code'     => $m['role_code'] ?? 'player',
                        'position_code' => $m['position_code'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('profile.show')->with('status', 'Команда сохранена');
    }

    public function update(Request $request, UserTeam $team)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        if ((int)$team->user_id !== (int)$user->id) abort(403);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'direction' => ['required', 'string', 'in:classic,beach'],
            'subtype'   => ['nullable', 'string', 'max:16'],
            'members'   => ['nullable', 'array', 'max:30'],
            'members.*.user_id'       => ['required', 'integer', 'exists:users,id'],
            'members.*.role_code'     => ['nullable', 'string', 'in:captain,player,reserve'],
            'members.*.position_code' => ['nullable', 'string', 'in:setter,outside,opposite,middle,libero'],
        ]);

        DB::transaction(function () use ($user, $team, $data) {
            $team->update([
                'name'      => $data['name'],
                'direction' => $data['direction'],
                'subtype'   => $data['subtype'] ?? $team->subtype,
            ]);

            // Rebuild members (keep captain)
            UserTeamMember::where('user_team_id', $team->id)
                ->where('user_id', '!=', $user->id)
                ->delete();

            foreach (($data['members'] ?? []) as $m) {
                if ((int)$m['user_id'] === (int)$user->id) continue;
                UserTeamMember::firstOrCreate(
                    ['user_team_id' => $team->id, 'user_id' => (int)$m['user_id']],
                    [
                        'role_code'     => $m['role_code'] ?? 'player',
                        'position_code' => $m['position_code'] ?? null,
                    ]
                );
            }

            // Update captain position if provided
            if (!empty($data['members'])) {
                foreach ($data['members'] as $m) {
                    if ((int)$m['user_id'] === (int)$user->id && !empty($m['position_code'])) {
                        UserTeamMember::where('user_team_id', $team->id)
                            ->where('user_id', $user->id)
                            ->update(['position_code' => $m['position_code']]);
                    }
                }
            }
        });

        $returnTo = $request->input('return_to');
        if ($returnTo && str_starts_with($returnTo, '/')) {
            return redirect($returnTo)->with('status', 'Команда обновлена');
        }
        return redirect()->route('profile.show')->with('status', 'Команда обновлена');
    }

    public function destroy(Request $request, UserTeam $team)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
        if ((int)$team->user_id !== (int)$user->id) abort(403);
        $team->delete();
        return back()->with('status', 'Команда удалена');
    }
}
