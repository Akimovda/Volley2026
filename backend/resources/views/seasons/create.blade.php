<x-voll-layout body_class="seasons-page">
<x-slot name="title">Создать сезон и лигу</x-slot>
<x-slot name="h1">Новый сезон</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('seasons.index') }}" itemprop="item"><span itemprop="name">Мои сезоны</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Создать</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<div class="container">
<div class="ramka">

    <div style="max-width:560px;margin:0 auto">

            <div class="card p-3 mb-3" style="background:rgba(41,103,186,.04);border:1px solid rgba(41,103,186,.15)">
                <div class="f-14" style="color:#6b7280">
                    <strong>Сезон</strong> — это серия регулярных турниров с накопительной статистикой, рейтингом игроков и системой промоушена между лигами.
                </div>
            </div>

            <form action="{{ route('seasons.store') }}" method="POST">
                @csrf

                <div class="card p-3 mb-3">
                    <h3 class="f-16 mb-2" style="margin-top:0">Основные настройки</h3>

                    <label class="b-600 f-14 mb-1 d-block">Название сезона</label>
                    <input type="text" name="name" id="name"
                           value="{{ old('name') }}"
                           placeholder="Например: Лига Среда — Весна 2026"
                           required>
                    @error('name') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror

                    <label class="b-600 f-14 mb-1 mt-2 d-block">Направление</label>
                    <select name="direction" id="direction">
                        <option value="classic" {{ old('direction') === 'classic' ? 'selected' : '' }}>🏐 Классический (6x6)</option>
                        <option value="beach" {{ old('direction') === 'beach' ? 'selected' : '' }}>🏖 Пляжный (2x2 / 3x3 / 4x4)</option>
                    </select>
                </div>

                <div class="card p-3 mb-3">
                    <h3 class="f-16 mb-2" style="margin-top:0">Даты проведения</h3>
                    <div class="row">
                        <div class="col-6">
                            <label class="b-600 f-14 mb-1 d-block">Начало</label>
                            <input type="date" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d')) }}">
                        </div>
                        <div class="col-6">
                            <label class="b-600 f-14 mb-1 d-block">Окончание</label>
                            <input type="date" name="ends_at" value="{{ old('ends_at') }}" placeholder="Оставьте пустым для бессрочного">
                        </div>
                    </div>
                    <div class="f-12 mt-1" style="color:#9ca3af">Окончание можно не указывать — сезон будет бессрочным</div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary p-3 f-16">Создать сезон</button>
                </div>
            </form>
    </div>

</div>
</div>

</x-voll-layout>