<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserMergeService;
use Illuminate\Http\Request;

class AdminUserDuplicatesController extends Controller
{
    public function __construct(private UserMergeService $mergeService) {}

    public function index()
    {
        $duplicates = $this->mergeService->findDuplicates();
        return view('admin.users.duplicates', compact('duplicates'));
    }

    public function merge(Request $request)
    {
        $request->validate([
            'primary_id'   => ['required', 'integer', 'exists:users,id'],
            'secondary_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $primary   = User::findOrFail($request->primary_id);
        $secondary = User::findOrFail($request->secondary_id);

        try {
            $this->mergeService->merge($primary, $secondary);
            return redirect()->route('admin.users.duplicates')
                ->with('status', "✅ Аккаунт #{$secondary->id} объединён с #{$primary->id}.");
        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('error', '❌ Ошибка: ' . $e->getMessage());
        }
    }
}
