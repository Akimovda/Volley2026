<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin audits</h2>
            <a class="v-btn" href="{{ route('admin.dashboard') }}">Dashboard</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="v-card">
                <div class="v-card__body">
                    <form class="grid grid-cols-1 md:grid-cols-6 gap-3" method="GET" action="{{ route('admin.audits.index') }}">
                        <input class="v-input md:col-span-2" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="action contains..." />
                        <input class="v-input" name="admin_user_id" value="{{ $filters['admin_user_id'] ?? '' }}" placeholder="admin_user_id" />
                        <input class="v-input" name="target_type" value="{{ $filters['target_type'] ?? '' }}" placeholder="target_type (e.g. user)" />
                        <input class="v-input" name="target_id" value="{{ $filters['target_id'] ?? '' }}" placeholder="target_id" />
                        <input class="v-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" />
                        <input class="v-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" />

                        <div class="md:col-span-6 flex gap-2">
                            <button class="v-btn v-btn--primary" type="submit">Filter</button>
                            <a class="v-btn" href="{{ route('admin.audits.index') }}">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="v-card">
                <div class="v-card__body overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-gray-600">
                            <tr>
                                <th class="text-left py-2 pr-4">ID</th>
                                <th class="text-left py-2 pr-4">At</th>
                                <th class="text-left py-2 pr-4">Admin</th>
                                <th class="text-left py-2 pr-4">Action</th>
                                <th class="text-left py-2 pr-4">Target</th>
                                <th class="text-left py-2 pr-4">IP</th>
                                <th class="text-left py-2 pr-4">Meta</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-800">
                            @forelse ($audits as $a)
                                <tr class="border-t align-top">
                                    <td class="py-2 pr-4">{{ $a->id }}</td>

                                    <td class="py-2 pr-4 whitespace-nowrap">
                                        {{ \Illuminate\Support\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}
                                    </td>

                                    <td class="py-2 pr-4">
                                        <div class="font-medium">{{ $a->admin_name ?? ('#'.$a->admin_user_id) }}</div>
                                        <div class="text-xs text-gray-500">{{ $a->admin_email ?? '' }}</div>
                                    </td>

                                    <td class="py-2 pr-4 whitespace-nowrap">{{ $a->action }}</td>

                                    <td class="py-2 pr-4 whitespace-nowrap">
                                        {{ $a->target_type }}:{{ $a->target_id }}
                                    </td>

                                    <td class="py-2 pr-4 whitespace-nowrap">{{ $a->ip ?? '—' }}</td>

                                    <td class="py-2 pr-4">
                                        @php
                                            $meta = $a->meta;
                                            $metaArr = null;

                                            if (is_string($meta) && $meta !== '') {
                                                $decoded = json_decode($meta, true);
                                                $metaArr = is_array($decoded) ? $decoded : null;
                                            } elseif (is_array($meta)) {
                                                $metaArr = $meta;
                                            }
                                        @endphp

                                        @if ($metaArr)
                                            <pre class="text-xs whitespace-pre-wrap">{{ json_encode($metaArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @elseif(is_string($meta) && $meta !== '')
                                            <div class="text-xs">{{ $meta }}</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="border-t">
                                    <td class="py-4 text-gray-500" colspan="7">No audits found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $audits->links() }}
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
