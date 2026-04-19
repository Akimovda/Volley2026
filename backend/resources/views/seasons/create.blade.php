<x-voll-layout body_class="seasons-page">
<x-slot name="title">Создать сезон</x-slot>
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

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <form action="{{ route('seasons.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">Название сезона</label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="Лига Среда — Апрель 2026" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="direction" class="form-label">Направление</label>
                    <select name="direction" id="direction" class="form-select">
                        <option value="classic" {{ old('direction') === 'classic' ? 'selected' : '' }}>Классический (6x6)</option>
                        <option value="beach" {{ old('direction') === 'beach' ? 'selected' : '' }}>Пляжный (2x2 / 3x3 / 4x4)</option>
                    </select>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label for="starts_at" class="form-label">Начало</label>
                        <input type="date" name="starts_at" id="starts_at" class="form-control" value="{{ old('starts_at') }}">
                    </div>
                    <div class="col-6">
                        <label for="ends_at" class="form-label">Окончание</label>
                        <input type="date" name="ends_at" id="ends_at" class="form-control" value="{{ old('ends_at') }}">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Создать сезон</button>
            </form>
        </div>
    </div>

</div>
</div>

</x-voll-layout>
