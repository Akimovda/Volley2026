<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'action' => trim((string) $request->get('action', '')),
            'admin_user_id' => trim((string) $request->get('admin_user_id', '')),
            'target_type' => trim((string) $request->get('target_type', '')),
            'target_id' => trim((string) $request->get('target_id', '')),
            'date_from' => trim((string) $request->get('date_from', '')),
            'date_to' => trim((string) $request->get('date_to', '')),
        ];

        $builder = DB::table('admin_audits')
            ->leftJoin('users as admins', 'admins.id', '=', 'admin_audits.admin_user_id')
            ->select([
                'admin_audits.*',
                'admins.name as admin_name',
                'admins.email as admin_email',
            ])
            ->when($filters['action'] !== '', fn ($q) => $q->where('admin_audits.action', 'like', '%' . $filters['action'] . '%'))
            ->when($filters['admin_user_id'] !== '', fn ($q) => $q->where('admin_audits.admin_user_id', (int) $filters['admin_user_id']))
            ->when($filters['target_type'] !== '', fn ($q) => $q->where('admin_audits.target_type', $filters['target_type']))
            ->when($filters['target_id'] !== '', fn ($q) => $q->where('admin_audits.target_id', $filters['target_id']))
            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('admin_audits.created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('admin_audits.created_at', '<=', $filters['date_to']))
            ->orderByDesc('admin_audits.id');

        $audits = $builder->paginate(50)->withQueryString();

        return view('admin.audits.index', [
            'audits' => $audits,
            'filters' => $filters,
        ]);
    }
}
