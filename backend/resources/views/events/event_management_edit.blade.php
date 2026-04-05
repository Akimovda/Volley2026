{{-- resources/views/events/event_management_edit.blade.php --}}
@php
  $isAdmin = (auth()->user()?->role ?? null) === 'admin';
@endphp

<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Редактирование мероприятия #{{ (int)$event->id }}
        </h2>
        <div class="text-sm text-gray-500 mt-1">
          Активных записей: <span class="font-semibold text-gray-800">{{ (int)$activeRegs }}</span>.
          Изменения не удаляют существующие записи.
        </div>
      </div>

      <a href="{{ route('events.create.event_management', ['tab'=>'mine']) }}"
         class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
        ← Назад
      </a>
    </div>
  </x-slot>

  <div class="py-10">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

      {{-- FLASH / ERRORS (как в create) --}}
      @if (session('status'))
        <div class="p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">
          {{ session('status') }}
        </div>
      @endif
      @if (session('error'))
        <div class="p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">
          {{ session('error') }}
        </div>
      @endif
      @if ($errors->any())
        <div class="p-3 rounded-lg bg-red-50 text-red-800 border border-red-100 text-sm">
          <div class="font-semibold mb-2">Ошибки:</div>
          <ul class="list-disc ml-5 space-y-1">
            @foreach ($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('events.event_management.update', ['event' => (int)$event->id]) }}">
          @csrf
          @method('PUT')

          {{-- Блок 1: основные поля --}}
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Название мероприятия</label>
              <input
                name="title"
                class="w-full rounded-lg border-gray-200"
                value="{{ old('title', (string)$event->title) }}"
                required
              >
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Начало</label>
              <input
                name="starts_at"
                type="datetime-local"
                class="w-full rounded-lg border-gray-200"
                value="{{ old('starts_at', $event->starts_at ? $event->starts_at->copy()->format('Y-m-d\TH:i') : '') }}"
                required
              >
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">Окончание</label>
              <input
                name="ends_at"
                type="datetime-local"
                class="w-full rounded-lg border-gray-200"
                value="{{ old('ends_at', $event->ends_at ? $event->ends_at->copy()->format('Y-m-d\TH:i') : '') }}"
              >
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700 mb-2">Timezone</label>
              <input
                name="timezone"
                class="w-full rounded-lg border-gray-200"
                value="{{ old('timezone', (string)$event->timezone) }}"
                required
              >
              <div class="text-xs text-gray-500 mt-1">Напр. Europe/Moscow, Europe/Berlin…</div>
            </div>
          </div>

          {{-- Блок 2: локация (делаем красиво как create) --}}
          <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-white">
            <div class="flex items-center justify-between gap-3">
              <div class="font-semibold text-sm text-gray-800">Локация</div>
              @if($isAdmin)
                <a href="{{ route('admin.locations.create') }}"
                   class="text-sm font-semibold text-blue-600 hover:text-blue-700">
                  + Создать локацию
                </a>
              @endif
            </div>

            <div class="mt-3">
              <select name="location_id" id="location_id" class="w-full rounded-lg border-gray-200" required>
                <option value="">— выбрать —</option>
                @foreach(($locations ?? []) as $loc)
                  @php
                    $thumb = $loc->getFirstMediaUrl('photos', 'thumb');
                    if (empty($thumb)) $thumb = $loc->getFirstMediaUrl('photos');
                  @endphp
                  <option
                    value="{{ (int)$loc->id }}"
                    @selected((int)old('location_id', (int)$event->location_id)===(int)$loc->id)
                    data-name="{{ e((string)$loc->name) }}"
                    data-city="{{ e((string)($loc->city?->name ?? '')) }}"
                    data-address="{{ e((string)($loc->address ?? '')) }}"
                    data-short="{{ e((string)($loc->short_text ?? '')) }}"
                    data-lat="{{ $loc->lat ?? '' }}"
                    data-lng="{{ $loc->lng ?? '' }}"
                    data-thumb="{{ e((string)$thumb) }}"
                  >
                    {{ $loc->name }}@if(!empty($loc->address)) — {{ $loc->address }}@endif
                  </option>
                @endforeach
              </select>
            </div>

            {{-- preview --}}
            <div id="location_preview" class="mt-3 hidden">
              <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div class="aspect-[16/9] bg-gray-100">
                  <img id="location_preview_img" src="" alt="" class="w-full h-full object-cover hidden">
                  <div id="location_preview_noimg" class="w-full h-full flex items-center justify-center text-sm text-gray-400">
                    Нет фото
                  </div>
                </div>
                <div class="p-4">
                  <div class="font-semibold text-gray-900" id="location_preview_name"></div>
                  <div class="mt-1 text-sm text-gray-600" id="location_preview_meta"></div>
                  <div class="mt-2 text-sm text-gray-700" id="location_preview_short" style="display:none;"></div>
                  <div class="mt-3 rounded-2xl overflow-hidden border border-gray-100" id="location_preview_map_wrap" style="display:none;">
                    <iframe
                      id="location_preview_map"
                      src=""
                      class="w-full"
                      style="height: 220px;"
                      loading="lazy"
                      referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                  </div>
                  <div class="mt-2 text-xs text-gray-500" id="location_preview_coords" style="display:none;"></div>
                </div>
              </div>
            </div>
          </div>
            {{-- Levels --}}
            <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-gray-50">
              <div class="font-semibold text-sm text-gray-800">Уровень допуска</div>
              <div class="text-xs text-gray-500 mt-1">
                Если заполнены оба — диапазон “от и до”. Если заполнено одно — ограничение будет по нему.
              </div>
            
              <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 rounded-xl border border-gray-100 bg-white">
                  <div class="text-xs font-semibold text-gray-600 mb-2">Classic</div>
                  <div class="flex gap-3">
                    <div class="w-1/2">
                      <label class="block text-xs font-semibold text-gray-600 mb-1">От (min)</label>
                      <input type="number" name="classic_level_min" min="0" max="10"
                             class="w-full rounded-lg border-gray-200"
                             value="{{ old('classic_level_min', is_null($event->classic_level_min) ? '' : (int)$event->classic_level_min) }}">
                    </div>
                    <div class="w-1/2">
                      <label class="block text-xs font-semibold text-gray-600 mb-1">До (max)</label>
                      <input type="number" name="classic_level_max" min="0" max="10"
                             class="w-full rounded-lg border-gray-200"
                             value="{{ old('classic_level_max', is_null($event->classic_level_max) ? '' : (int)$event->classic_level_max) }}">
                    </div>
                  </div>
                </div>
            
                <div class="p-4 rounded-xl border border-gray-100 bg-white">
                  <div class="text-xs font-semibold text-gray-600 mb-2">Beach</div>
                  <div class="flex gap-3">
                    <div class="w-1/2">
                      <label class="block text-xs font-semibold text-gray-600 mb-1">От (min)</label>
                      <input type="number" name="beach_level_min" min="0" max="10"
                             class="w-full rounded-lg border-gray-200"
                             value="{{ old('beach_level_min', is_null($event->beach_level_min) ? '' : (int)$event->beach_level_min) }}">
                    </div>
                    <div class="w-1/2">
                      <label class="block text-xs font-semibold text-gray-600 mb-1">До (max)</label>
                      <input type="number" name="beach_level_max" min="0" max="10"
                             class="w-full rounded-lg border-gray-200"
                             value="{{ old('beach_level_max', is_null($event->beach_level_max) ? '' : (int)$event->beach_level_max) }}">
                    </div>
                  </div>
                </div>
              </div>
            </div>

          {{-- Блок 3: регистрация + max players --}}
          <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-gray-50">
            <div class="font-semibold text-sm text-gray-800">Регистрация</div>

            <div class="mt-3 flex flex-col md:flex-row gap-3">
              @php $allowRegVal = old('allow_registration', (int)((bool)$event->allow_registration)); @endphp
              <label class="inline-flex items-center gap-3">
                <input type="radio" name="allow_registration" value="1" @checked((string)$allowRegVal==='1')>
                <span class="text-sm font-semibold">Разрешить</span>
              </label>
              <label class="inline-flex items-center gap-3">
                <input type="radio" name="allow_registration" value="0" @checked((string)$allowRegVal==='0')>
                <span class="text-sm font-semibold">Выключить</span>
              </label>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Max players (game settings)</label>
                <input
                  name="game_max_players"
                  type="number"
                  min="0"
                  class="w-full rounded-lg border-gray-200"
                  value="{{ old('game_max_players', (int)($event->gameSettings?->max_players ?? 0)) }}"
                >
                <div class="text-xs text-gray-500 mt-1">
                  Лимит мест берём из <span class="font-mono">event_game_settings.max_players</span>.
                </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Min players (опционально)</label>
                <input
                  name="game_min_players"
                  type="number"
                  min="0"
                  class="w-full rounded-lg border-gray-200"
                  value="{{ old('game_min_players', (int)($event->gameSettings?->min_players ?? 0)) }}"
                >
              </div>
            </div>
          </div>
         {{-- Помощник записи --}}
          <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-gray-50">
            <div class="font-semibold text-sm text-gray-800 mb-3">Помощник записи 🤖</div>
 
            <label class="inline-flex items-center gap-3 cursor-pointer">
              <input type="hidden" name="bot_assistant_enabled" value="0">
              <input
                type="checkbox"
                name="bot_assistant_enabled"
                value="1"
                id="bot_assistant_enabled_edit"
                @checked((bool) old('bot_assistant_enabled', $event->bot_assistant_enabled ?? false))
                onchange="document.getElementById('bot_assistant_settings_edit').style.display = this.checked ? '' : 'none'"
              >
              <span class="text-sm font-semibold text-gray-700">Включить</span>
            </label>
 
            <div
              id="bot_assistant_settings_edit"
              class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4"
              style="{{ old('bot_assistant_enabled', $event->bot_assistant_enabled ?? false) ? '' : 'display:none' }}"
            >
              {{-- Порог --}}
              <div class="p-4 rounded-xl border border-gray-100 bg-white">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Порог запуска:
                  <strong id="bot_threshold_val_edit" class="text-blue-600">
                    {{ old('bot_assistant_threshold', $event->bot_assistant_threshold ?? 10) }}%
                  </strong>
                </label>
                <input
                  type="range"
                  name="bot_assistant_threshold"
                  id="bot_assistant_threshold_edit"
                  min="5" max="30" step="5"
                  value="{{ old('bot_assistant_threshold', $event->bot_assistant_threshold ?? 10) }}"
                  class="w-full"
                  oninput="document.getElementById('bot_threshold_val_edit').textContent = this.value + '%'"
                >
                <div class="text-xs text-gray-500 mt-1">
                  Если за сутки &lt; порога живых игроков — боты включаются (5–30%).
                </div>
              </div>
 
              {{-- Макс. заполнение --}}
              <div class="p-4 rounded-xl border border-gray-100 bg-white">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Макс. заполнение ботами:
                  <strong id="bot_fill_val_edit" class="text-blue-600">
                    {{ old('bot_assistant_max_fill_pct', $event->bot_assistant_max_fill_pct ?? 40) }}%
                  </strong>
                </label>
                <input
                  type="range"
                  name="bot_assistant_max_fill_pct"
                  id="bot_assistant_max_fill_pct_edit"
                  min="10" max="60" step="10"
                  value="{{ old('bot_assistant_max_fill_pct', $event->bot_assistant_max_fill_pct ?? 40) }}"
                  class="w-full"
                  oninput="document.getElementById('bot_fill_val_edit').textContent = this.value + '%'"
                >
                <div class="text-xs text-gray-500 mt-1">
                  Боты не займут больше этого % мест. Минимум 2 места всегда свободны.
                </div>
              </div>
            </div>
          </div>
          <div class="mt-6 flex justify-end gap-3">
            <a class="v-btn v-btn--secondary" href="{{ route('events.create.event_management', ['tab'=>'mine']) }}">Отмена</a>
            <button class="v-btn v-btn--primary" type="submit">Сохранить</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- JS: location preview (взято из create один-в-один) --}}
  <script>
    (function () {
      function hasClass(el, c) { return el && el.classList && el.classList.contains(c); }
      function addClass(el, c) { if (el && el.classList) el.classList.add(c); }
      function removeClass(el, c) { if (el && el.classList) el.classList.remove(c); }
      function trim(s) { return String(s || '').replace(/^\s+|\s+$/g, ''); }

      var sel = document.getElementById('location_id');
      var wrap = document.getElementById('location_preview');
      var img = document.getElementById('location_preview_img');
      var noimg = document.getElementById('location_preview_noimg');
      var nameEl = document.getElementById('location_preview_name');
      var metaEl = document.getElementById('location_preview_meta');
      var shortEl = document.getElementById('location_preview_short');
      var mapWrap = document.getElementById('location_preview_map_wrap');
      var mapEl = document.getElementById('location_preview_map');
      var coordsEl = document.getElementById('location_preview_coords');

      function updatePreview() {
        if (!sel) return;

        var opt = null;
        if (sel.selectedIndex >= 0) opt = sel.options[sel.selectedIndex];

        if (!opt || !opt.value) {
          if (wrap) addClass(wrap, 'hidden');
          if (mapEl) mapEl.src = '';
          return;
        }

        var name = opt.getAttribute('data-name') || '';
        var city = opt.getAttribute('data-city') || '';
        var address = opt.getAttribute('data-address') || '';
        var shortText = opt.getAttribute('data-short') || '';
        var thumb = opt.getAttribute('data-thumb') || '';
        var lat = trim(opt.getAttribute('data-lat') || '');
        var lng = trim(opt.getAttribute('data-lng') || '');

        if (wrap) removeClass(wrap, 'hidden');
        if (nameEl) nameEl.textContent = name;

        var metaParts = [];
        if (city) metaParts.push(city);
        if (address) metaParts.push(address);
        if (metaEl) metaEl.textContent = metaParts.join(' • ');

        if (shortEl) {
          if (trim(shortText)) { shortEl.style.display = ''; shortEl.textContent = shortText; }
          else { shortEl.style.display = 'none'; shortEl.textContent = ''; }
        }

        if (thumb && img && noimg) {
          img.src = thumb;
          removeClass(img, 'hidden');
          addClass(noimg, 'hidden');
        } else if (img && noimg) {
          img.src = '';
          addClass(img, 'hidden');
          removeClass(noimg, 'hidden');
        }

        var hasCoords = (lat !== '' && lng !== '' && !isNaN(Number(lat)) && !isNaN(Number(lng)));
        if (mapWrap && mapEl && coordsEl) {
          if (hasCoords) {
            mapWrap.style.display = '';
            coordsEl.style.display = '';
            coordsEl.textContent = 'Координаты: ' + lat + ', ' + lng;
            mapEl.src = 'https://www.openstreetmap.org/export/embed.html?layer=mapnik&marker=' +
              encodeURIComponent(lat) + ',' + encodeURIComponent(lng) + '&zoom=16';
          } else {
            mapWrap.style.display = 'none';
            coordsEl.style.display = 'none';
            coordsEl.textContent = '';
            mapEl.src = '';
          }
        }
      }

      if (sel) sel.addEventListener('change', updatePreview);
      updatePreview();
    })();
  </script>
</x-app-layout>
