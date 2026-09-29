<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandThemeService;
use App\Support\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminAppController extends Controller
{
    public function index()
    {
        $brands = Brand::query()->where('is_default', false)->orderBy('id')->get();

        return view('admin.apps.index', compact('brands'));
    }

    public function edit(Brand $brand)
    {
        abort_if($brand->is_default, 404);

        return view('admin.apps.edit', [
            'brand'  => $brand,
            'groups' => BrandThemeService::BASE,
        ]);
    }

    public function update(Request $request, Brand $brand)
    {
        abort_if($brand->is_default, 404);

        $hex = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $rules = [
            'logo_day'   => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'logo_night' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'app_icon'   => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
        foreach (['day', 'night'] as $mode) {
            foreach (BrandThemeService::keys() as $key) {
                $rules["theme.$mode.$key"] = $hex;
            }
        }
        $data = $request->validate($rules, [
            'regex' => __('admin.app_err_hex'),
            'max'   => __('admin.app_err_file_size'),
            'mimes' => __('admin.app_err_file_type'),
        ]);

        $before = ['theme' => $brand->theme, 'logo_day' => $brand->logo_day_path, 'logo_night' => $brand->logo_night_path, 'icon' => $brand->app_icon_path];

        $theme = [];
        foreach (['day', 'night'] as $mode) {
            foreach (BrandThemeService::keys() as $key) {
                $val = strtoupper(trim((string) ($data['theme'][$mode][$key] ?? '')));
                if ($val !== '') {
                    $theme[$mode][$key] = $val;
                }
            }
        }
        $brand->theme = $theme ?: null;

        $this->applyFile($request, $brand, 'logo_day', 'logo_day_path', 'remove_logo_day');
        $this->applyFile($request, $brand, 'logo_night', 'logo_night_path', 'remove_logo_night');
        $this->applyFile($request, $brand, 'app_icon', 'app_icon_path', 'remove_app_icon');

        $brand->save();

        AdminAuditLogger::log('brand.theme.update', 'brand', $brand->id, [
            'slug'   => $brand->slug,
            'before' => $before,
            'after'  => ['theme' => $brand->theme, 'logo_day' => $brand->logo_day_path, 'logo_night' => $brand->logo_night_path, 'icon' => $brand->app_icon_path],
        ]);

        return redirect()->route('admin.apps.edit', $brand)->with('status', __('admin.app_saved'));
    }

    private function applyFile(Request $request, Brand $brand, string $input, string $column, string $removeFlag): void
    {
        $file = $request->file($input);

        if ($file) {
            $this->deleteOld($brand->{$column});
            $brand->{$column} = 'storage/' . $file->store('brands/' . $brand->slug, 'public');

            return;
        }

        if ($request->boolean($removeFlag)) {
            $this->deleteOld($brand->{$column});
            $brand->{$column} = null;
        }
    }

    /** Удаляем только загруженные через этот экран файлы (storage/brands/...), не статику из public/icons. */
    private function deleteOld(?string $path): void
    {
        if ($path && str_starts_with($path, 'storage/brands/')) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));
        }
    }
}
