{{-- resources/views/staff/logs.blade.php --}}
<x-voll-layout body_class="staff-logs-page">
    <x-slot name="title">Логи действий Staff</x-slot>
    <x-slot name="h1">📋 Логи действий помощников</x-slot>

    <div class="container">
        <div class="row row2">
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka">
                        @include('profile._menu', [
                            'menuUser'       => auth()->user(),
                            'isEditingOther' => false,
                            'activeMenu'     => 'org_dashboard',
                        ])
                    </div>
                </div>
            </div>
            <div class="col-lg-8 col-xl-9 order-1">

                <div class="ramka">
                    @if($logs->isEmpty())
                    <div class="alert alert-info">Логов пока нет.</div>
                    @else
                    <div class="form">
                        @foreach($logs as $log)
                        <div class="card mb-1">
                            <div class="d-flex between fvc">
                                <div>
                                    <div class="b-600">{{ \App\Services\StaffLogService::actionLabel($log->action) }}</div>
                                    @if($log->description)
                                    <div class="f-14 mt-05" style="opacity:.7;">{{ $log->description }}</div>
                                    @endif
                                    <div class="f-13 mt-05" style="opacity:.5;">
                                        👤 {{ trim($log->staff->first_name . ' ' . $log->staff->last_name) }}
                                        · {{ $log->created_at->format('d.m.Y H:i') }}
                                        @if($log->entity_type && $log->entity_id)
                                        · {{ $log->entity_type }} #{{ $log->entity_id }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                        <div class="mt-2">{{ $logs->links() }}</div>
                    </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</x-voll-layout>
