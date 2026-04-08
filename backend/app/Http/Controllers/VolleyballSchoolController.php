<?php
namespace App\Http\Controllers;

use App\Models\VolleyballSchool;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable;

class VolleyballSchoolController extends Controller
{
    // Список запрещённых слов (базовый набор)
    private array $badWords = [
        'хуй','пизда','блядь','ебать','еблан','сука','пиздец','мудак','залупа','хуйня',
        'ёбаный','ёб','нахуй','похуй','пиздёж','ёбать','ёбаный','блять','пездец',
    ];

    private function containsBadWords(string $text): bool
    {
        $lower = mb_strtolower($text);
        foreach ($this->badWords as $word) {
            if (str_contains($lower, $word)) return true;
        }
        return false;
    }

    public function index()
    {
        $schools = VolleyballSchool::query()
            ->where('is_published', true)
            ->with(['organizer:id,first_name,last_name', 'media'])
            ->orderBy('name')
            ->paginate(12);

        return view('volleyball_school.index', compact('schools'));
    }

    public function show(string $slug)
    {
        $school = VolleyballSchool::where('slug', $slug)
            ->where('is_published', true)
            ->with(['organizer:id,first_name,last_name,avatar_media_id', 'media'])
            ->firstOrFail();

        $todayUtc = CarbonImmutable::now('UTC')->startOfDay();

        $occurrences = collect();
        if (Schema::hasTable('event_occurrences')) {
            $occurrences = EventOccurrence::query()
                ->whereHas('event', fn($q) => $q
                    ->where('organizer_id', $school->organizer_id)
                    ->where(fn($w) => $w->whereNull('is_private')->orWhere('is_private', false))
                )
                ->whereNull('cancelled_at')
                ->where(fn($q) => $q->whereNull('is_cancelled')->orWhere('is_cancelled', false))
                ->where('starts_at', '>=', $todayUtc)
                ->with([
                    'event' => fn($q) => $q->with([
                        'location:id,name,address,city_id',
                        'location.city:id,name',
                        'gameSettings',
                        'media',
                        'organizer:id,first_name,last_name',
                    ]),
                ])
                ->orderBy('starts_at')
                ->limit(20)
                ->get();
        }

        return view('volleyball_school.show', compact('school', 'occurrences'));
    }

    public function create(Request $request)
    {
        $user = $request->user();

        // Не-админ уже имеет школу
        if (!$user->isAdmin()) {
            $existing = VolleyballSchool::where('organizer_id', $user->id)->first();
            if ($existing) {
                return redirect()->route('volleyball_school.edit')
                    ->with('status', 'У вас уже есть страница школы.');
            }
        }

        $organizers = $user->isAdmin()
            ? User::whereIn('role', ['organizer', 'admin'])->orderBy('first_name')->get()
            : collect();

        return view('volleyball_school.create', compact('organizers'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'slug'           => ['required', 'string', 'max:60', 'alpha_dash', 'unique:volleyball_schools,slug'],
            'direction'      => ['required', 'in:classic,beach,both'],
            'description'    => ['nullable', 'string', 'max:5000'],
            'city_id'        => ['nullable', 'integer', 'exists:cities,id'],
            'city_name'      => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:20', 'regex:/^\+7\d{10}$/'],
            'email'          => ['nullable', 'email', 'max:100'],
            'website'        => ['nullable', 'url', 'max:200'],
            'logo_media_id'  => ['nullable', 'integer'],
            'cover_media_id' => ['nullable', 'integer'],
            'organizer_id'   => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'phone.regex' => 'Телефон должен быть в формате +7XXXXXXXXXX.',
            'slug.alpha_dash' => 'Slug может содержать только латиницу, цифры и дефис.',
            'slug.unique' => 'Этот URL уже занят, выберите другой.',
        ]);

        // Проверка нецензурных слов
        if ($this->containsBadWords($data['name'])) {
            return back()->withInput()->withErrors(['name' => 'Название содержит недопустимые слова.']);
        }
        if (!empty($data['description']) && $this->containsBadWords(strip_tags($data['description']))) {
            return back()->withInput()->withErrors(['description' => 'Описание содержит недопустимые слова.']);
        }

        // Организатор
        $organizerId = $user->id;
        if ($user->isAdmin() && !empty($data['organizer_id'])) {
            $organizerId = (int) $data['organizer_id'];
        }

        // Город — сохраняем название для отображения
        $cityName = null;
        if (!empty($data['city_id'])) {
            $city = \App\Models\City::find($data['city_id']);
            $cityName = $city?->name;
        } elseif (!empty($data['city_name'])) {
            $cityName = $data['city_name'];
        }

        $school = VolleyballSchool::create([
            'organizer_id' => $organizerId,
            'slug'         => $data['slug'],
            'name'         => $data['name'],
            'direction'    => $data['direction'],
            'description'  => $data['description'] ?? null,
            'city'         => $cityName,
            'phone'        => $data['phone'] ?? null,
            'email'        => $data['email'] ?? null,
            'website'      => $data['website'] ?? null,
            'is_published' => true,
        ]);

        // Медиа из медиатеки пользователя
        $organizer = User::find($organizerId);
        if ($organizer) {
            if (!empty($data['logo_media_id'])) {
                $media = $organizer->getMedia('photos')->firstWhere('id', (int)$data['logo_media_id']);
                if ($media) {
                    $school->addMediaFromDisk($media->getPath(), 'public')
                        ->preservingOriginal()
                        ->toMediaCollection('logo');
                }
            }
            if (!empty($data['cover_media_id'])) {
                $media = $organizer->getMedia('photos')->firstWhere('id', (int)$data['cover_media_id']);
                if ($media) {
                    $school->addMediaFromDisk($media->getPath(), 'public')
                        ->preservingOriginal()
                        ->toMediaCollection('cover');
                }
            }
        }

        return redirect()->route('volleyball_school.show', $school->slug)
            ->with('status', 'Страница школы создана!');
    }

    public function edit(Request $request)
    {
        $user = $request->user();
        $school = $user->isAdmin()
            ? VolleyballSchool::where('id', $request->query('id', 0))
                ->orWhere('organizer_id', $user->id)
                ->firstOrFail()
            : VolleyballSchool::where('organizer_id', $user->id)->firstOrFail();

        $organizer = User::find($school->organizer_id);
        $userPhotos = $organizer?->getMedia('photos')->sortByDesc('created_at') ?? collect();

        $allSchools = $user->isAdmin()
            ? VolleyballSchool::with('organizer:id,first_name,last_name')->orderBy('name')->get()
            : collect();

        return view('volleyball_school.edit', compact('school', 'userPhotos', 'allSchools'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $school = $user->isAdmin()
            ? VolleyballSchool::findOrFail($request->input('school_id', 0))
            : VolleyballSchool::where('organizer_id', $user->id)->firstOrFail();

        $data = $request->validate([
            'school_id'      => ['nullable', 'integer'],
            'name'           => ['required', 'string', 'max:100'],
            'direction'      => ['required', 'in:classic,beach,both'],
            'description'    => ['nullable', 'string', 'max:5000'],
            'city_id'        => ['nullable', 'integer', 'exists:cities,id'],
            'city_name'      => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:20', 'regex:/^\+7\d{10}$/'],
            'email'          => ['nullable', 'email', 'max:100'],
            'website'        => ['nullable', 'url', 'max:200'],
            'logo_media_id'  => ['nullable', 'integer'],
            'cover_media_id' => ['nullable', 'integer'],
            'is_published'   => ['sometimes', 'boolean'],
        ], [
            'phone.regex' => 'Телефон должен быть в формате +7XXXXXXXXXX.',
        ]);

        if ($this->containsBadWords($data['name'])) {
            return back()->withInput()->withErrors(['name' => 'Название содержит недопустимые слова.']);
        }
        if (!empty($data['description']) && $this->containsBadWords(strip_tags($data['description']))) {
            return back()->withInput()->withErrors(['description' => 'Описание содержит недопустимые слова.']);
        }

        $cityName = $school->city;
        if (!empty($data['city_id'])) {
            $city = \App\Models\City::find($data['city_id']);
            $cityName = $city?->name;
        } elseif (!empty($data['city_name'])) {
            $cityName = $data['city_name'];
        }

        $school->update([
            'name'         => $data['name'],
            'direction'    => $data['direction'],
            'description'  => $data['description'] ?? null,
            'city'         => $cityName,
            'phone'        => $data['phone'] ?? null,
            'email'        => $data['email'] ?? null,
            'website'      => $data['website'] ?? null,
            'is_published' => (bool)($data['is_published'] ?? false),
        ]);

        $organizer = User::find($school->organizer_id);
        if ($organizer) {
            if (!empty($data['logo_media_id'])) {
                $media = $organizer->getMedia('photos')->firstWhere('id', (int)$data['logo_media_id']);
                if ($media) {
                    $school->clearMediaCollection('logo');
                    $school->addMediaFromDisk($media->getPath(), 'public')
                        ->preservingOriginal()
                        ->toMediaCollection('logo');
                }
            }
            if (!empty($data['cover_media_id'])) {
                $media = $organizer->getMedia('photos')->firstWhere('id', (int)$data['cover_media_id']);
                if ($media) {
                    $school->clearMediaCollection('cover');
                    $school->addMediaFromDisk($media->getPath(), 'public')
                        ->preservingOriginal()
                        ->toMediaCollection('cover');
                }
            }
        }

        return redirect()->route('volleyball_school.show', $school->slug)
            ->with('status', 'Страница обновлена!');
    }
}