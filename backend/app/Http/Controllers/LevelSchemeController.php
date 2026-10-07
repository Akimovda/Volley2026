<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\LevelLabelService;
use App\Support\AdminAuditLogger;
use Illuminate\Http\Request;

/** Свои названия/цвета 7 уровней: бренд приложения (админ) и организатор/школа (виджеты, приложения). */
class LevelSchemeController extends Controller
{
    public function updateBrand(Request $request, Brand $brand)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $this->store($request, 'brand', (int) $brand->id);
        AdminAuditLogger::log('brand.levels.update', 'brand', $brand->id, ['slug' => $brand->slug]);

        return redirect()->route('admin.apps.edit', $brand)->with('status', __('levelscheme.saved'));
    }

    public function updateOrganizer(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['organizer', 'admin'], true), 403);

        $this->store($request, 'organizer', (int) $user->id);

        return redirect()->route('profile.widget')->with('status', __('levelscheme.saved'));
    }

    /** Публичный JSON для приложения: GET /api/app/level-scheme?organizer_id= (бренд — по User-Agent, DetectBrand). */
    public function api(Request $request)
    {
        $org = $request->integer('organizer_id') ?: null;

        return response()->json(LevelLabelService::payload($org))
            ->header('Cache-Control', 'public, max-age=300');
    }

    private function store(Request $request, string $type, int $ownerId): void
    {
        if ($request->boolean('reset')) {
            LevelLabelService::save($type, $ownerId, []);

            return;
        }

        $hex = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $rules = [];
        for ($l = 1; $l <= LevelLabelService::LEVELS; $l++) {
            $rules["levels.$l.name_ru"] = ['required', 'string', 'max:60'];
            $rules["levels.$l.name_en"] = ['nullable', 'string', 'max:60'];
            $rules["levels.$l.short_ru"] = ['nullable', 'string', 'max:20'];
            $rules["levels.$l.short_en"] = ['nullable', 'string', 'max:20'];
            $rules["levels.$l.color"] = $hex;
            $rules["levels.$l.text_color"] = $hex;
        }
        $data = $request->validate($rules, ['required' => __('levelscheme.err_all_seven'), 'regex' => __('admin.app_err_hex')]);

        $rows = [];
        foreach ($data['levels'] as $l => $r) {
            $rows[(int) $l] = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $r);
            foreach (['color', 'text_color'] as $c) {
                $rows[(int) $l][$c] = !empty($r[$c]) ? strtoupper($r[$c]) : null;
            }
        }
        LevelLabelService::save($type, $ownerId, $rows);
    }
}
