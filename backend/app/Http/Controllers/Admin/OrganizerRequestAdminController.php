<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizerRequestAdminController extends Controller
{
    public function __construct()
    {
        // Дублируем защиту на уровне контроллера (на случай, если роуты когда-то поменяются)
        $this->middleware(['auth', 'can:approve-organizer-request']);
    }

    public function index()
    {
        $requests = DB::table('organizer_requests')
            ->join('users', 'users.id', '=', 'organizer_requests.user_id')
            ->leftJoin('users as reviewers', 'reviewers.id', '=', 'organizer_requests.reviewed_by')
            ->select([
                'organizer_requests.id',
                'organizer_requests.user_id',
                'organizer_requests.status',
                'organizer_requests.message',
                'organizer_requests.created_at',
                'organizer_requests.reviewed_by',
                'organizer_requests.reviewed_at',

                'users.email',
                'users.telegram_username',
                'users.first_name',
                'users.last_name',
                'users.role',

                DB::raw('reviewers.email as reviewer_email'),
            ])
            ->orderByDesc('organizer_requests.id')
            ->get();

        return view('admin.organizer_requests.index', [
            'requests' => $requests,
        ]);
    }

    public function approve(Request $request, int $requestId)
    {
        $row = DB::table('organizer_requests')->where('id', $requestId)->first();

        if (!$row) {
            return back()->with('status', 'Заявка не найдена.');
        }
        if ($row->status !== 'pending') {
            return back()->with('status', 'Заявка уже обработана.');
        }

        DB::transaction(function () use ($request, $row) {
            DB::table('organizer_requests')
                ->where('id', $row->id)
                ->update([
                    'status' => 'approved',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('users')
                ->where('id', $row->user_id)
                ->update([
                    'role' => 'organizer',
                    'updated_at' => now(),
                ]);
        });

        return back()->with('status', 'Заявка одобрена. Пользователь стал Organizer.');
    }

    public function reject(Request $request, int $requestId)
    {
        $row = DB::table('organizer_requests')->where('id', $requestId)->first();

        if (!$row) {
            return back()->with('status', 'Заявка не найдена.');
        }
        if ($row->status !== 'pending') {
            return back()->with('status', 'Заявка уже обработана.');
        }

        DB::transaction(function () use ($request, $row) {
            DB::table('organizer_requests')
                ->where('id', $row->id)
                ->update([
                    'status' => 'rejected',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return back()->with('status', 'Заявка отклонена.');
    }
}
