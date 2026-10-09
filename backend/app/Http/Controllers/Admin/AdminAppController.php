<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandThemeService;
use App\Support\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'menuGroups' => config('brand_menu.groups'),
            'previewUrl' => url('/events') . '?' . http_build_query(['_bp' => $brand->id, '_bpe' => $exp = time() + 7200, '_bps' => BrandThemeService::previewSig((int) $brand->id, $exp)]),
            'fontScales' => BrandThemeService::FONT_SCALES,
            'tokenGroups' => config('brand_tokens.groups'),
            'fonts' => config('brand_tokens.fonts'),
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
            foreach (array_merge(BrandThemeService::keys(), array_keys(BrandThemeService::tokens())) as $key) {
                $rules["theme.$mode.$key"] = $hex;
            }
        }
        $rules['theme.font_scale'] = ['nullable', 'integer', Rule::in(BrandThemeService::FONT_SCALES)];
        $rules['theme.font'] = ['nullable', Rule::in(array_keys((array) config('brand_tokens.fonts')))];
        $rules['menu.visible']         = ['nullable', 'array'];
        $rules['menu.visible.*']       = ['string', Rule::in($this->menuCatalogPaths())];
        $rules['menu.links']           = ['nullable', 'array', 'max:' . (int) config('brand_menu.max_links', 15)];
        $rules['menu.links.*.title_ru'] = ['nullable', 'string', 'max:60'];
        $rules['menu.links.*.title_en'] = ['nullable', 'string', 'max:60'];
        $rules['menu.links.*.url']      = ['nullable', 'string', 'max:500', 'regex:~^(https?://|/(?!/))\S*$~i'];
        $rules['menu.links.*.place']    = ['nullable', Rule::in(['site', 'user'])];
        $rules['menu.links.*.new_tab']  = ['nullable', 'boolean'];

        $data = $request->validate($rules, [
            'menu.links.*.url.regex' => __('admin.app_err_url'),
            'menu.links.max'         => __('admin.app_err_links_max'),
            'regex' => __('admin.app_err_hex'),
            'max'   => __('admin.app_err_file_size'),
            'mimes' => __('admin.app_err_file_type'),
        ]);

        $before = ['theme' => $brand->theme, 'menu' => $brand->menu, 'logo_day' => $brand->logo_day_path, 'logo_night' => $brand->logo_night_path, 'icon' => $brand->app_icon_path];

        $theme = [];
        foreach (['day', 'night'] as $mode) {
            foreach (array_merge(BrandThemeService::keys(), array_keys(BrandThemeService::tokens())) as $key) {
                $val = strtoupper(trim((string) ($data['theme'][$mode][$key] ?? '')));
                if ($val !== '') {
                    $theme[$mode][$key] = $val;
                }
            }
        }
        if (!empty($data['theme']['font'])) {
            $theme['font'] = $data['theme']['font'];
        }
        $scale = (int) ($data['theme']['font_scale'] ?? 100);
        if ($scale !== 100) {
            $theme['font_scale'] = $scale;
        }
        $brand->theme = $theme ?: null;

        // Меню правим только если форма меню реально пришла (marker menu_form) — иначе оставляем как есть.
        if ($request->has('menu_form')) {
            $links = [];
            foreach ((array) ($data['menu']['links'] ?? []) as $i => $l) {
                $titleRu = trim((string) ($l['title_ru'] ?? ''));
                $url = trim((string) ($l['url'] ?? ''));
                if ($titleRu === '' && $url === '') {
                    continue; // пустая строка формы
                }
                if ($titleRu === '' || $url === '') {
                    throw ValidationException::withMessages(["menu.links.$i.url" => __('admin.app_err_link_incomplete')]);
                }
                $links[] = [
                    'title_ru' => $titleRu,
                    'title_en' => trim((string) ($l['title_en'] ?? '')),
                    'url'      => $url,
                    'place'    => ($l['place'] ?? 'site') === 'user' ? 'user' : 'site',
                    'new_tab'  => !empty($l['new_tab']),
                ];
            }
            // В форме отмечены ПОКАЗЫВАЕМЫЕ пункты; храним скрытые = каталог минус отмеченные.
            $hidden = array_values(array_diff($this->menuCatalogPaths(), (array) ($data['menu']['visible'] ?? [])));
            $brand->menu = ($hidden || $links) ? ['hidden' => $hidden, 'links' => $links] : null;
        }

        $this->applyFile($request, $brand, 'logo_day', 'logo_day_path', 'remove_logo_day');
        $this->applyFile($request, $brand, 'logo_night', 'logo_night_path', 'remove_logo_night');
        $this->applyFile($request, $brand, 'app_icon', 'app_icon_path', 'remove_app_icon');

        $brand->save();

        AdminAuditLogger::log('brand.theme.update', 'brand', $brand->id, [
            'slug'   => $brand->slug,
            'before' => $before,
            'after'  => ['theme' => $brand->theme, 'menu' => $brand->menu, 'logo_day' => $brand->logo_day_path, 'logo_night' => $brand->logo_night_path, 'icon' => $brand->app_icon_path],
        ]);

        return redirect()->route('admin.apps.edit', $brand)->with('status', __('admin.app_saved'));
    }

    /** CSS несохранённой темы из текущего состояния формы — для живого предпросмотра (ничего не пишет). */
    public function previewCss(Request $request, Brand $brand)
    {
        abort_if($brand->is_default, 404);

        $theme = [];
        foreach (['day', 'night'] as $mode) {
            foreach (array_merge(BrandThemeService::keys(), array_keys(BrandThemeService::tokens())) as $key) {
                $v = strtoupper(trim((string) $request->input("theme.$mode.$key", '')));
                if (preg_match('/^#[0-9A-F]{6}$/', $v)) {
                    $theme[$mode][$key] = $v;
                }
            }
        }
        $font = (string) $request->input('theme.font', '');
        if ($font !== '' && config('brand_tokens.fonts.' . $font)) {
            $theme['font'] = $font;
        }

        $scale = (int) $request->input('theme.font_scale', 100);
        if ($scale !== 100 && in_array($scale, BrandThemeService::FONT_SCALES, true)) {
            $theme['font_scale'] = $scale;
        }

        $copy = $brand->replicate();
        $copy->theme = $theme ?: null;

        return response(app(BrandThemeService::class)->css($copy, false), 200, ['Content-Type' => 'text/css; charset=UTF-8']);
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

    /** @return string[] */
    private function menuCatalogPaths(): array
    {
        $paths = [];
        foreach ((array) config('brand_menu.groups') as $group) {
            foreach ($group['items'] as [$path]) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
