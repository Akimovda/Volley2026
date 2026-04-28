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
            'primary_id'    => ['required', 'integer', 'exists:users,id'],
            'secondary_ids' => ['required', 'array', 'min:1'],
            'secondary_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $primary     = User::findOrFail($request->primary_id);
        $secondaries = User::whereIn('id', $request->secondary_ids)
            ->where('id', '!=', $primary->id)
            ->get();

        if ($secondaries->isEmpty()) {
            return redirect()->back()->with('error', '❌ Не найдены вторичные аккаунты.');
        }

        try {
            $merged = [];
            foreach ($secondaries as $secondary) {
                $this->mergeService->merge($primary, $secondary);
                $merged[] = '#' . $secondary->id;
            }
            $list = implode(', ', $merged);
            return redirect()->route('admin.users.duplicates')
                ->with('status', "✅ Аккаунты {$list} объединены с #{$primary->id}.");
        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('error', '❌ Ошибка: ' . $e->getMessage());
        }
    }
}
