<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use Closure;
use Illuminate\Http\Request;

class DetectBrand
{
    public function handle(Request $request, Closure $next)
    {
        $brand = Brand::detectByUserAgent($request->userAgent());

        app()->instance(Brand::class, $brand);
        $request->attributes->set('brand', $brand);

        return $next($request);
    }
}
