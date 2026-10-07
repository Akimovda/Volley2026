<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Services\BrandThemeService;
use Closure;
use Illuminate\Http\Request;

class DetectBrand
{
    public function handle(Request $request, Closure $next)
    {
        $brand = Brand::detectByUserAgent($request->userAgent());

        // Предпросмотр «Настройка App»: подписанная ссылка (id бренда + срок) подменяет бренд на этот запрос
        $pid = (int) $request->query('_bp');
        $exp = (int) $request->query('_bpe');
        if ($pid && $exp > time() && hash_equals(BrandThemeService::previewSig($pid, $exp), (string) $request->query('_bps'))) {
            $brand = Brand::query()->find($pid) ?: $brand;
        }

        app()->instance(Brand::class, $brand);
        $request->attributes->set('brand', $brand);

        return $next($request);
    }
}
