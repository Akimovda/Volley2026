<x-voll-layout body_class="trainer-analytics-page">

    <x-slot name="title">{{ __('trainers.analytics_title') }} — {{ $membership->trainer?->name }}</x-slot>
    <x-slot name="h1">{{ __('trainers.analytics_title') }}</x-slot>
    <x-slot name="h2">{{ $membership->trainer?->name ?? ('#' . $membership->user_id) }} · {{ $school->name }}</x-slot>

    <div class="container">

        {{-- Период --}}
        <div class="ramka">
            <div class="d-flex" style="gap:8px;flex-wrap:wrap;">
                @foreach(['month','last_month','quarter','year'] as $p)
                <a href="?period={{ $p }}"
                   class="btn btn-small {{ $period === $p ? '' : 'btn-secondary' }}">{{ __('trainers.analytics_period_' . $p) }}</a>
                @endforeach
            </div>

            <form method="GET" class="form d-flex fvc mt-1" style="gap:8px;flex-wrap:wrap;">
                <input type="hidden" name="period" value="custom">
                <div>
                    <label class="f-13 mb-05">{{ __('trainers.analytics_from_label') }}</label>
                    <input type="date" name="from" value="{{ $from->format('Y-m-d') }}">
                </div>
                <div>
                    <label class="f-13 mb-05">{{ __('trainers.analytics_to_label') }}</label>
                    <input type="date" name="to" value="{{ $to->format('Y-m-d') }}">
                </div>
                <button type="submit" class="btn btn-secondary btn-small" style="align-self:flex-end;">{{ __('trainers.analytics_period_apply') }}</button>
            </form>

            <div class="f-13 mt-1" style="opacity:.6;">
                {{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }}
            </div>
        </div>

        @if($metrics['sessions_without_rate'] > 0)
        <div class="ramka">
            <div class="alert" style="background:#fffbeb;border-left:4px solid #f59e0b;">
                {{ __('trainers.analytics_without_rate_badge', ['n' => $metrics['sessions_without_rate']]) }}
            </div>
        </div>
        @endif

        @if($metrics['sessions_count'] === 0)
        <div class="ramka">
            <div class="f-15" style="opacity:.6;">{{ __('trainers.analytics_no_sessions') }}</div>
        </div>
        @else
        <div class="ramka">
            <div class="row row2">
                <div class="col-3 mb-2">
                    <div class="card" style="height:auto;">
                        <div class="f-13" style="opacity:.6;">{{ __('trainers.analytics_m_hours') }}</div>
                        <div class="f-24 b-600">{{ $metrics['hours'] }}</div>
                    </div>
                </div>
                <div class="col-3 mb-2">
                    <div class="card" style="height:auto;">
                        <div class="f-13" style="opacity:.6;">{{ __('trainers.analytics_m_sessions') }}</div>
                        <div class="f-24 b-600">{{ $metrics['sessions_count'] }}</div>
                    </div>
                </div>
                <div class="col-3 mb-2">
                    <div class="card" style="height:auto;">
                        <div class="f-13" style="opacity:.6;">{{ __('trainers.analytics_m_unique_players') }}</div>
                        <div class="f-24 b-600">{{ $metrics['unique_players'] }}</div>
                    </div>
                </div>
                <div class="col-3 mb-2">
                    <div class="card" style="height:auto;">
                        <div class="f-13" style="opacity:.6;">{{ __('trainers.analytics_m_retention') }}</div>
                        <div class="f-24 b-600">{{ $metrics['retention_pct'] !== null ? $metrics['retention_pct'] . '%' : __('trainers.analytics_no_data') }}</div>
                    </div>
                </div>
                <div class="col-3 mb-2">
                    <div class="card" style="height:auto;">
                        <div class="f-13" style="opacity:.6;">{{ __('trainers.analytics_m_fill_rate') }}</div>
                        <div class="f-24 b-600">{{ $metrics['fill_rate_pct'] !== null ? $metrics['fill_rate_pct'] . '%' : __('trainers.analytics_no_data') }}</div>
                    </div>
                </div>
                <div class="col-3 mb-2">
                    <div class="card" style="height:auto;">
                        <div class="f-13" style="opacity:.6;">{{ __('trainers.analytics_m_to_pay') }}</div>
                        <div class="f-24 b-600">{{ number_format($metrics['to_pay'], 2, '.', ' ') }} ₽</div>
                    </div>
                </div>

                @if($isOrganizerView)
                <div class="col-3 mb-2">
                    <div class="card" style="height:auto;">
                        <div class="f-13" style="opacity:.6;">{{ __('trainers.analytics_m_revenue') }}</div>
                        <div class="f-24 b-600">{{ number_format($metrics['revenue'], 2, '.', ' ') }} ₽</div>
                    </div>
                </div>
                <div class="col-3 mb-2">
                    <div class="card" style="height:auto;">
                        <div class="f-13" style="opacity:.6;">{{ __('trainers.analytics_m_margin') }}</div>
                        <div class="f-24 b-600" style="{{ $metrics['margin'] < 0 ? 'color:#e53e3e' : '' }}">{{ number_format($metrics['margin'], 2, '.', ' ') }} ₽</div>
                    </div>
                </div>
                @else
                <div class="col-6 mb-2">
                    <div class="f-13" style="opacity:.5;padding:1rem;">{{ __('trainers.analytics_finance_hidden') }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif

    </div>

</x-voll-layout>
