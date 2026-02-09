<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class AdminLocationController extends Controller
{
    public function index()
    {
        $locations = Location::query()
            ->orderBy('city')
            ->orderBy('name')
            ->paginate(30);

        return view('admin.locations.index', compact('locations'));
    }

    public function create()
    {
        return view('admin.locations.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'address'    => ['nullable', 'string', 'max:255'],
            'city'       => ['nullable', 'string', 'max:255'],
            'timezone'   => ['required', 'string', 'max:64'],

            'short_text' => ['nullable', 'string', 'max:255'],
            'long_text'  => ['nullable', 'string'],

            'lat'        => ['nullable', 'numeric', 'between:-90,90'],
            'lng'        => ['nullable', 'numeric', 'between:-180,180'],

            'photos'     => ['nullable', 'array'],
            'photos.*'   => ['nullable', 'image', 'max:5120'],
        ]);

        $location = new Location();
        $location->organizer_id = null; // системная локация
        $location->name = $data['name'];
        $location->address = $data['address'] ?? null;
        $location->city = $data['city'] ?? null;
        $location->timezone = $data['timezone'];

        $location->short_text = $data['short_text'] ?? null;
        $location->long_text = $data['long_text'] ?? null;

        $location->lat = array_key_exists('lat', $data) ? $data['lat'] : null;
        $location->lng = array_key_exists('lng', $data) ? $data['lng'] : null;

        $location->save();

        // photos[]
        $files = $request->file('photos', []);
        foreach ($files as $file) {
            if (!$file) continue;
            $location->addMedia($file)->toMediaCollection('photos');
        }

        return redirect()
            ->route('admin.locations.index')
            ->with('status', 'Локация создана ✅');
    }

    public function edit(Location $location)
    {
        $photos = $location->getMedia('photos')
            ->sortBy(fn ($m) => (int)($m->order_column ?? 0))
            ->values();

        return view('admin.locations.edit', compact('location', 'photos'));
    }

    public function update(Request $request, Location $location)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'address'    => ['nullable', 'string', 'max:255'],
            'city'       => ['nullable', 'string', 'max:255'],
            'timezone'   => ['required', 'string', 'max:64'],

            'short_text' => ['nullable', 'string', 'max:255'],
            'long_text'  => ['nullable', 'string'],

            'lat'        => ['nullable', 'numeric', 'between:-90,90'],
            'lng'        => ['nullable', 'numeric', 'between:-180,180'],

            'photos'     => ['nullable', 'array'],
            'photos.*'   => ['nullable', 'image', 'max:5120'],
        ]);

        $location->name = $data['name'];
        $location->address = $data['address'] ?? null;
        $location->city = $data['city'] ?? null;
        $location->timezone = $data['timezone'];

        $location->short_text = $data['short_text'] ?? null;
        $location->long_text = $data['long_text'] ?? null;

        $location->lat = array_key_exists('lat', $data) ? $data['lat'] : null;
        $location->lng = array_key_exists('lng', $data) ? $data['lng'] : null;

        $location->save();

        // photos[]
        $files = $request->file('photos', []);
        foreach ($files as $file) {
            if (!$file) continue;
            $location->addMedia($file)->toMediaCollection('photos');
        }

        return back()->with('status', 'Локация сохранена ✅');
    }

    public function destroy(Location $location)
    {
        // удалит location + media (если настроено каскадно через медиалайн,
        // обычно MediaLibrary удаляет записи media при удалении модели через events)
        $location->delete();

        return redirect()
            ->route('admin.locations.index')
            ->with('status', 'Локация удалена ✅');
    }
}
