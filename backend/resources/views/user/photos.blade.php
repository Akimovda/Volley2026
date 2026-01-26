{{-- resources/views/user/photos.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Мои фото</h2>
            <a href="{{ route('profile.show') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                ← Назад в профиль
            </a>
        </div>
    </x-slot>

    {{-- FLASH --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6">
        @if (session('status'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">
                <div class="font-semibold mb-1">Ошибки:</div>
                <ul class="list-disc ml-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- FilePond styles (CDN) --}}
    <link href="https://unpkg.com/filepond@^4/dist/filepond.css" rel="stylesheet" />
    <link href="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css" rel="stylesheet" />

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Current avatar --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center gap-5">
                    <img
                        src="{{ $user->profile_photo_url }}"
                        alt="avatar"
                        class="rounded-full border border-gray-100"
                        style="width:96px;height:96px;object-fit:cover;"
                    />
                    <div class="min-w-0">
                        <div class="text-lg font-bold text-gray-900 truncate">
                            {{ method_exists($user, 'displayName') ? $user->displayName() : ($user->name ?? '—') }}
                        </div>
                        <div class="text-sm text-gray-600 mt-1">Текущий аватар</div>
                    </div>
                </div>
            </div>

            {{-- Upload --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-start justify-between gap-6 flex-col md:flex-row">
                    <div class="min-w-0">
                        <div class="text-lg font-bold text-gray-900">Загрузить фото</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Обрезка 1:1, поворот, авто‑сжатие. Можно “Загрузить” или “Отменить”.
                        </div>
                    </div>
                </div>

                <form id="photoUploadForm"
                      action="{{ route('user.photos.store') }}"
                      method="POST"
                      enctype="multipart/form-data"
                      class="mt-5">
                    @csrf

                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 mb-3">
                        <input id="make_avatar" name="make_avatar" type="checkbox" class="rounded border-gray-300" />
                        <span>Сделать это фото аватаром</span>
                    </label>

                    {{-- name="photo" важно для валидатора --}}
                    <input type="file" name="photo" id="photo" accept="image/*" />
                </form>

                <div class="text-xs text-gray-500 mt-3">
                    Если увидишь 413 от nginx — увеличь <code>client_max_body_size</code> (вы это уже правили).
                </div>
            </div>

            {{-- Gallery --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="text-lg font-bold text-gray-900">Галерея</div>
                    <div class="text-sm text-gray-600">Всего: {{ $photos->count() }}</div>
                </div>

                @if($photos->isEmpty())
                    <div class="mt-6 text-gray-600">
                        Пока нет фото. Загрузи первое — и оно станет аватаром автоматически.
                    </div>
                @else
                    {{-- 2/3/4/6/8 в ряд адаптивно --}}
                    <div class="mt-6 vb-gallery-grid">
                        @foreach($photos as $m)
                            @php
                                $thumbUrl = method_exists($m, 'hasGeneratedConversion') && $m->hasGeneratedConversion('thumb')
                                    ? $m->getUrl('thumb')
                                    : $m->getUrl();
                                $isAvatar = $avatar && (int)$avatar->id === (int)$m->id;
                            @endphp

                            <div class="group relative rounded-xl overflow-hidden border border-gray-100 bg-gray-50 hover:shadow-lg transition">
                                <a href="{{ $m->getUrl() }}" target="_blank" class="block">
                                    <img
                                        src="{{ $thumbUrl }}"
                                        alt="photo"
                                        class="w-full transition-transform duration-200 group-hover:scale-105"
                                        style="aspect-ratio: 1 / 1; object-fit: cover;"
                                        loading="lazy"
                                    />
                                </a>

                                {{-- badge --}}
                                @if($isAvatar)
                                    <div class="absolute top-2 left-2 text-xs font-bold px-2 py-1 rounded-lg bg-indigo-600 text-white shadow">
                                        Аватар
                                    </div>
                                @endif

                                {{-- actions (видимы всегда) --}}
                                <div class="absolute inset-x-0 bottom-0 p-2 bg-gradient-to-t from-black/60 to-transparent">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('user.photos.setAvatar', ['media' => $m->id]) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center px-3 py-2 rounded-lg text-xs font-extrabold bg-indigo-600 text-white hover:bg-indigo-700">
                                                Сделать аватаром
                                            </button>
                                        </form>

                                        <form method="POST"
                                              action="{{ route('user.photos.destroy', ['media' => $m->id]) }}"
                                              onsubmit="return confirm('Удалить фото?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center px-3 py-2 rounded-lg text-xs font-extrabold bg-white text-gray-900 border border-gray-200 hover:bg-gray-100">
                                                Удалить
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>

    {{-- FilePond scripts (CDN) --}}
    <script src="https://unpkg.com/filepond@^4/dist/filepond.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-exif-orientation/dist/filepond-plugin-image-exif-orientation.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-crop/dist/filepond-plugin-image-crop.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-resize/dist/filepond-plugin-image-resize.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-transform/dist/filepond-plugin-image-transform.js"></script>

    <script>
        FilePond.registerPlugin(
            FilePondPluginImagePreview,
            FilePondPluginImageExifOrientation,
            FilePondPluginImageCrop,
            FilePondPluginImageResize,
            FilePondPluginImageTransform
        );

        const input = document.querySelector('#photo');

        FilePond.create(input, {
            allowMultiple: false,

            // нет авто-аплоада → появятся "Загрузить / Отменить"
            instantUpload: false,
            allowProcess: true,
            allowRevert: false,

            // UI
            labelIdle: 'Перетащи фото или <span class="filepond--label-action">выбери</span>',
            labelButtonProcessItem: 'Загрузить',
            labelButtonRemoveItem: 'Отменить',
            labelFileProcessing: 'Загрузка…',
            labelFileProcessingComplete: 'Готово ✅',

            imagePreviewHeight: 180,

            // Crop 1:1 + resize
            allowImageCrop: true,
            imageCropAspectRatio: '1:1',
            allowImageResize: true,
            imageResizeTargetWidth: 1024,
            imageResizeTargetHeight: 1024,
            imageResizeMode: 'cover',

            // output jpeg
            imageTransformOutputMimeType: 'image/jpeg',
            imageTransformOutputQuality: 0.9,

            // важно: поле = photo
            name: 'photo',

            server: {
                process: {
                    url: @json(route('user.photos.store')),
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
                    withCredentials: true,

                    // докидываем make_avatar (0/1)
                    ondata: (formData) => {
                        const makeAvatar = document.querySelector('#make_avatar')?.checked ? 1 : 0;
                        formData.append('make_avatar', makeAvatar);
                        return formData;
                    },

                    // обновим страницу чтобы сразу увидеть новое фото/аватар
                    onload: () => { window.location.reload(); return ''; },
                },
                revert: null,
            },
        });
    </script>
</x-app-layout>
