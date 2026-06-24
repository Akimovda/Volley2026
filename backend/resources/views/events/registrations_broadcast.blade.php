{{-- resources/views/events/registrations_broadcast.blade.php --}}
<x-voll-layout>

    <x-slot name="title">{{ __('events.broadcast_title') }} — {{ $event->title }}</x-slot>
    <x-slot name="h1">{{ __('events.broadcast_title') }}</x-slot>
    <x-slot name="h2">{{ $event->title }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.registrations.index', ['event' => $event->id, 'occurrence' => $occurrenceId]) }}" itemprop="item">
                <span itemprop="name">{{ __('events.broadcast_back') }}</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>

    <div class="container form">

        {{-- FLASH: отчёт об отправке --}}
        @if(session('broadcast_sent'))
            @php $sent = session('broadcast_sent'); @endphp
            <div class="ramka">
                <div class="alert alert-success">
                    <strong>✅ {{ __('events.broadcast_queued', ['n' => $sent['queued']]) }}</strong>
                </div>
                <table class="table mt-1">
                    <thead>
                        <tr>
                            <th>{{ __('events.broadcast_channel_col') }}</th>
                            <th class="text-center">{{ __('events.broadcast_with_binding') }}</th>
                            <th class="text-center">{{ __('events.broadcast_no_binding') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(in_array('in_app', $sent['channels']))
                        <tr>
                            <td>In-app</td>
                            <td class="text-center" colspan="2" style="color:var(--color-muted,#6b7280);font-size:1.3rem">
                                {{ __('events.broadcast_inapp_note', ['n' => $sent['queued']]) }}
                            </td>
                        </tr>
                        @endif
                        @foreach(['telegram' => 'Telegram', 'vk' => 'VK', 'max' => 'MAX', 'push' => 'Push'] as $ch => $label)
                            @if(in_array($ch, $sent['channels']))
                                @php
                                    $with = match($ch) {
                                        'telegram' => $sent['reach']['tg'],
                                        'vk'       => $sent['reach']['vk'],
                                        'max'      => $sent['reach']['max'],
                                        'push'     => $sent['reach']['push'],
                                    };
                                    $without = $sent['reach']['total'] - $with;
                                @endphp
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-center">{{ $with }}</td>
                                    <td class="text-center" style="color:var(--color-muted,#9ca3af)">{{ $without }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-1">
                    <a href="{{ route('events.registrations.index', ['event' => $event->id, 'occurrence' => $occurrenceId]) }}"
                       class="btn btn-secondary">← {{ __('events.broadcast_back') }}</a>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="ramka">
                <div class="alert alert-danger">
                    <ul class="mb-0" style="padding-left:1.5rem">
                        @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- СЧЁТЧИК ПОЛУЧАТЕЛЕЙ --}}
        <div class="ramka">
            <h3 class="mt-0">Получатели</h3>
            <div class="row row2">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div style="font-size:2.8rem;font-weight:700;color:var(--color-primary,#2967BA)">{{ $counts['mainCount'] }}</div>
                        <div class="f-13 text-muted mt-05">{{ __('events.broadcast_main_count', ['n' => '']) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div style="font-size:2.8rem;font-weight:700;color:var(--color-primary,#2967BA)">{{ $counts['reserveCount'] }}</div>
                        <div class="f-13 text-muted mt-05">{{ __('events.broadcast_reserve_count', ['n' => '']) }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="f-13 text-muted">Telegram: <strong>{{ $reach['tg'] }}</strong></div>
                        <div class="f-13 text-muted">VK: <strong>{{ $reach['vk'] }}</strong></div>
                        <div class="f-13 text-muted">MAX: <strong>{{ $reach['max'] }}</strong></div>
                        <div class="f-13 text-muted">Push: <strong>{{ $reach['push'] }}</strong></div>
                        <div class="f-13 text-muted">In-app: <strong>{{ $reach['total'] }}</strong></div>
                        <div class="f-12 text-muted mt-05" style="opacity:.7">(основной + резерв)</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ФОРМА --}}
        @if($counts['mainCount'] === 0 && $counts['reserveCount'] === 0)
            <div class="ramka">
                <div class="alert alert-info">Нет активных участников для рассылки на эту дату.</div>
                <a href="{{ route('events.registrations.index', ['event' => $event->id, 'occurrence' => $occurrenceId]) }}"
                   class="btn btn-secondary">← {{ __('events.broadcast_back') }}</a>
            </div>
        @else
        <div class="ramka">
            <form method="POST"
                  action="{{ route('events.registrations.broadcast.send', ['event' => $event->id]) }}">
                @csrf
                <input type="hidden" name="occurrence_id" value="{{ $occurrenceId }}">

                {{-- Тема --}}
                <div class="row row2 mb-1">
                    <div class="col-md-12">
                        <label>{{ __('events.broadcast_subject') }} <span style="color:red">*</span></label>
                        <input type="text" name="title" required maxlength="255"
                               value="{{ old('title') }}"
                               placeholder="{{ __('events.broadcast_subject') }}">
                    </div>
                </div>

                {{-- Сообщение --}}
                <div class="row row2 mb-1">
                    <div class="col-md-12">
                        <label>{{ __('events.broadcast_body') }} <span style="color:red">*</span></label>
                        <textarea name="body" required maxlength="5000" rows="6"
                                  placeholder="{{ __('events.broadcast_body') }}"
                                  style="width:100%">{{ old('body') }}</textarea>
                    </div>
                </div>

                {{-- Каналы --}}
                <div class="mb-1">
                    <label>{{ __('events.broadcast_channels') }}</label>
                    @php
                        $channelOptions = [
                            'in_app'   => 'In-app — ' . $reach['total'] . ' чел. (все)',
                            'telegram' => 'Telegram — ' . $reach['tg'] . ' чел.',
                            'vk'       => 'VK — ' . $reach['vk'] . ' чел.',
                            'max'      => 'MAX — ' . $reach['max'] . ' чел.',
                            'push'     => 'Push — ' . $reach['push'] . ' чел.',
                        ];
                        $oldChannels = old('channels', ['in_app','telegram','vk','max','push']);
                    @endphp
                    @foreach($channelOptions as $val => $label)
                    <label class="checkbox-item">
                        <input type="checkbox" name="channels[]" value="{{ $val }}"
                               @checked(in_array($val, $oldChannels))>
                        <div class="custom-checkbox"></div>
                        <span>{{ $label }}</span>
                    </label>
                    @endforeach
                </div>

                {{-- Включить резерв --}}
                <div class="mb-1">
                    <label class="checkbox-item">
                        <input type="checkbox" name="include_reserve" value="1"
                               @checked(old('include_reserve', true))>
                        <div class="custom-checkbox"></div>
                        <span>{{ __('events.broadcast_include_reserve', ['n' => $counts['reserveCount']]) }}</span>
                    </label>
                </div>

                <div class="d-flex mt-1" style="gap:1rem;flex-wrap:wrap">
                    <button type="submit" class="btn">{{ __('events.broadcast_send') }}</button>
                    <a href="{{ route('events.registrations.index', ['event' => $event->id, 'occurrence' => $occurrenceId]) }}"
                       class="btn btn-secondary">← {{ __('events.broadcast_back') }}</a>
                </div>
            </form>
        </div>
        @endif

    </div>

</x-voll-layout>
