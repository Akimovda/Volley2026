<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicLocationController extends Controller
{
    /**
     * Публичный список локаций.
     */
    public function index(Request $request)
    {
        $locations = Location::query()
            ->whereNull('organizer_id') // публичные (системные) локации
            ->orderBy('city')
            ->orderBy('name')
            ->paginate(24);

        return view('locations.index', compact('locations'));
    }

    /**
     * Публичная страница локации: /locations/{id}-{slug}
     * + canonical redirect если slug неправильный.
     */
    public function show(Request $request, int $location, string $slug)
    {
        $loc = Location::query()
            ->whereNull('organizer_id')
            ->whereKey($location)
            ->firstOrFail();

        $canonicalSlug = $this->slugify($loc->name);

        if ($slug !== $canonicalSlug) {
            return redirect()
                ->route('locations.show', ['location' => $loc->id, 'slug' => $canonicalSlug])
                ->setStatusCode(301);
        }

        // фото (в порядке order_column если используете reorder)
        $photos = $loc->getMedia('photos')
            ->sortBy(fn ($m) => (int)($m->order_column ?? 0))
            ->values();

        return view('locations.show', [
            'location' => $loc,
            'photos' => $photos,
            'slug' => $canonicalSlug,
        ]);
    }

    private function slugify(string $value): string
    {
        $s = Str::slug($value, '-');
        return $s !== '' ? $s : 'location';
    }
}
