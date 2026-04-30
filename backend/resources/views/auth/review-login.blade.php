<x-voll-layout body_class="auth-page auth-login">
    <x-slot name="title">Вход</x-slot>
    <x-slot name="description">Вход в аккаунт</x-slot>
    <x-slot name="h1">Вход</x-slot>
    <x-slot name="h2">Введите данные</x-slot>
    <x-slot name="breadcrumbs"></x-slot>
    <x-slot name="t_description">Вход по email и паролю</x-slot>
    <x-slot name="style"></x-slot>
    <x-slot name="script"></x-slot>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card mt-4">
                    <div class="card-body p-4">
                        @if ($errors->any())
                            <div class="alert alert-danger mb-3">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="/auth/review-login">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email') }}" required autofocus>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Пароль</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Войти</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-voll-layout>
