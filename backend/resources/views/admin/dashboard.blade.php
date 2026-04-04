{{-- Admin Dashboard --}}
<x-voll-layout body_class="admin-dashboard-page">
    
    <x-slot name="title">
        Admin Dashboard
    </x-slot>
    
    <x-slot name="description">
        Панель управления администратора
    </x-slot>
    
    <x-slot name="canonical">
        {{ route('admin.dashboard') }}
    </x-slot>
    
    <x-slot name="style">
        <style>
            /* Дополнительные стили при необходимости */
        </style>
    </x-slot>
    
    <x-slot name="h1">
        Admin Dashboard
    </x-slot>
    
    <x-slot name="h2">
        Панель управления
    </x-slot>
    
    <x-slot name="t_description">
        Статистика и мониторинг системы
    </x-slot>
    
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">Дашборд</span>
            <meta itemprop="position" content="1">
        </li>
    </x-slot>
    
    <x-slot name="d_description">
        <div class="mt-3">
            <a href="{{ route('admin.users.index') }}" class="btn btn-primary">
                Пользователи
            </a>
        </div>
    </x-slot>
    
    <x-slot name="script">
        <script>
            // Дополнительные скрипты при необходимости
        </script>
    </x-slot>
    
    <div class="container">
        <div class="row">
            <div class="col-12">
                @if (session('status'))
                    <div class="alert alert-success mb-4">
                        {{ session('status') }}
                    </div>
                @endif
                
                <div class="row g-4 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="text-muted small">Всего пользователей</div>
                                <div class="fs-2 fw-bold">{{ $totalUsers }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="text-muted small">Регистрации</div>
                                <div class="fs-2 fw-bold">{{ $registeredToday }}</div>
                                <div class="text-muted small mt-1">сегодня</div>
                                <div class="text-muted small mt-2">7д: <strong>{{ $registered7 }}</strong> · 30д: <strong>{{ $registered30 }}</strong></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="text-muted small">Активные</div>
                                <div class="mt-2 small">за 7 дней: <strong>{{ $active7 }}</strong></div>
                                <div class="small">за 30 дней: <strong>{{ $active30 }}</strong></div>
                                <div class="text-muted small mt-2">метрика: updated_at</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="text-muted small">Провайдеры</div>
                                <div class="mt-2 small">TG only: <strong>{{ $tgOnly }}</strong></div>
                                <div class="small">VK only: <strong>{{ $vkOnly }}</strong></div>
                                <div class="small">TG+VK: <strong>{{ $both }}</strong></div>
                                <div class="small">none: <strong>{{ $none }}</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="fw-semibold fs-5 mb-3">Удаления аккаунтов</div>
                                @if (!$hasDeletedAt)
                                    <div class="text-muted small">
                                        В таблице users нет deleted_at → статистика удалений недоступна.
                                    </div>
                                @else
                                    <div class="small">Сегодня: <strong>{{ $deletedToday }}</strong></div>
                                    <div class="small">За 7 дней: <strong>{{ $deleted7 }}</strong></div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="fw-semibold fs-5 mb-3">Аудит</div>
                                
                                <div class="small text-muted mb-1">
                                    Привязки аккаунтов (7д):
                                    <strong>{{ $linkCount7 === null ? '—' : $linkCount7 }}</strong>
                                </div>
                                <div class="text-muted small mb-3">
                                    {{ $hasLinkAudits ? 'account_link_audits' : 'таблица отсутствует' }}
                                </div>
                                
                                <div class="small text-muted mb-1">
                                    Действия админа (7д):
                                    <strong>{{ $adminActions7 === null ? '—' : $adminActions7 }}</strong>
                                </div>
                                <div class="text-muted small">
                                    {{ $hasAdminAudits ? 'admin_audits' : 'таблица отсутствует' }}
                                </div>
                                
                                {{-- На следующем шаге сделаем admin/audits --}}
                                {{-- <div class="mt-4">
                                    <a class="btn btn-secondary" href="{{ route('admin.audits.index') }}">Audit log</a>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</x-voll-layout>