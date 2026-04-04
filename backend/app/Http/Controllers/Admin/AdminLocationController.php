<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminLocationController extends Controller
{
public function index()
{
    $q = Location::query()
        ->with(['city:id,name,region,country_code,timezone']) // добавили region и country_code
        ->orderByRaw('COALESCE((select name from cities where cities.id = locations.city_id), \'\') asc')
        ->orderBy('name');

    return view('admin.locations.index', [
        'locations' => $q->paginate(30)->withQueryString(),
    ]);
}

    public function create()
    {
        // можно передать дефолтный город "Москва" (если у вас есть такой флаг/поиск)
        return view('admin.locations.create');
    }

public function store(Request $request)
{
    $data = $request->validate([
        'name'           => ['required', 'string', 'max:255'],
        'address'        => ['nullable', 'string', 'max:255'],
        'city_id'        => ['required', 'integer', 'exists:cities,id'],
        'timezone'       => ['nullable', 'string', 'max:64'],
        'short_text'     => ['nullable', 'string', 'max:255'],
        'long_text'      => ['nullable', 'string'],
        'long_text_full' => ['nullable', 'string'], // ДОБАВЛЕНО
        'note'           => ['nullable', 'string'], // ДОБАВЛЕНО
        'lat'            => ['nullable', 'numeric', 'between:-90,90'],
        'lng'            => ['nullable', 'numeric', 'between:-180,180'],
        'photos'         => ['nullable', 'array'],
        'photos.*'       => ['nullable', 'image', 'max:5120'],
    ]);

    $city = City::query()->whereKey((int)$data['city_id'])->first();

    $location = new Location();
    $location->organizer_id = null;
    $location->name = $data['name'];
    $location->address = $data['address'] ?? null;

    if (Schema::hasColumn('locations', 'city_id')) {
        $location->city_id = (int)$data['city_id'];
    }

    if (Schema::hasColumn('locations', 'timezone')) {
        $tz = trim((string)($data['timezone'] ?? ''));
        if ($tz === '') $tz = (string)($city->timezone ?? 'Europe/Moscow');
        $location->timezone = $tz;
    }

    $location->short_text = $data['short_text'] ?? null;
    $location->long_text  = $data['long_text'] ?? null;
    $location->long_text_full = $data['long_text_full'] ?? null; // ДОБАВЛЕНО
    $location->note = $data['note'] ?? null; // ДОБАВЛЕНО
    $location->lat = array_key_exists('lat', $data) ? $data['lat'] : null;
    $location->lng = array_key_exists('lng', $data) ? $data['lng'] : null;

    $location->save();

    foreach ($request->file('photos', []) as $file) {
        if (!$file) continue;
        $location->addMedia($file)->toMediaCollection('photos');
    }

    return redirect()
        ->route('admin.locations.index')
        ->with('status', 'Локация создана ✅');
}

    public function edit(Location $location)
    {
        $location->load(['city:id,name,region,country_code,timezone']);

        $photos = $location->getMedia('photos')
            ->sortBy(fn ($m) => (int)($m->order_column ?? 0))
            ->values();

        return view('admin.locations.edit', compact('location', 'photos'));
    }

public function update(Request $request, Location $location)
{
    $data = $request->validate([
        'name'           => ['required', 'string', 'max:255'],
        'address'        => ['nullable', 'string', 'max:255'],
        'city_id'        => ['required', 'integer', 'exists:cities,id'],
        'timezone'       => ['nullable', 'string', 'max:64'],
        'short_text'     => ['nullable', 'string', 'max:255'],
        'long_text'      => ['nullable', 'string'],
        'long_text_full' => ['nullable', 'string'], // ДОБАВИТЬ
        'note'           => ['nullable', 'string'], // ДОБАВИТЬ
        'lat'            => ['nullable', 'numeric', 'between:-90,90'],
        'lng'            => ['nullable', 'numeric', 'between:-180,180'],
        'photos'         => ['nullable', 'array'],
        'photos.*'       => ['nullable', 'image', 'max:5120'],
    ]);

    $city = City::query()->whereKey((int)$data['city_id'])->first();

    $location->name = $data['name'];
    $location->address = $data['address'] ?? null;

    if (Schema::hasColumn('locations', 'city_id')) {
        $location->city_id = (int)$data['city_id'];
    }

    if (Schema::hasColumn('locations', 'timezone')) {
        $tz = trim((string)($data['timezone'] ?? ''));
        if ($tz === '') $tz = (string)($city->timezone ?? $location->timezone ?? 'Europe/Moscow');
        $location->timezone = $tz;
    }

    $location->short_text = $data['short_text'] ?? null;
    $location->long_text  = $data['long_text'] ?? null;
    $location->long_text_full = $data['long_text_full'] ?? null; // ДОБАВИТЬ
    $location->note = $data['note'] ?? null; // ДОБАВИТЬ
    $location->lat = array_key_exists('lat', $data) ? $data['lat'] : null;
    $location->lng = array_key_exists('lng', $data) ? $data['lng'] : null;

    $location->save();

    foreach ($request->file('photos', []) as $file) {
        if (!$file) continue;
        $location->addMedia($file)->toMediaCollection('photos');
    }

    return back()->with('status', 'Локация сохранена ✅');
}

    public function destroy(Location $location)
    {
        $location->delete();

        return redirect()
            ->route('admin.locations.index')
            ->with('status', 'Локация удалена ✅');
    }
}