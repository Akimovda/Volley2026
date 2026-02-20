{{-- resources/views/admin/locations/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Локации</h2>
            <a href="{{ route('admin.locations.create') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                + Добавить
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 py-10">
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

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left p-3">ID</th>
                        <th class="text-left p-3">Город</th>
                        <th class="text-left p-3">Название</th>
                        <th class="text-left p-3">Адрес</th>
                        <th class="text-right p-3">Действия</th>
                    </tr>
                </thead>

                <tbody>
                @foreach($locations as $loc)
                    <tr class="border-t">
                        <td class="p-3">{{ $loc->id }}</td>
                        <td class="p-3">{{ $loc->city }}</td>
                        <td class="p-3 font-semibold">
                            <a href="{{ route('admin.locations.edit', $loc) }}" class="hover:underline">
                                {{ $loc->name }}
                            </a>
                        </td>
                        <td class="p-3">{{ $loc->address }}</td>
                        <td class="p-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.locations.edit', $loc) }}"
                                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 bg-white hover:bg-gray-50">
                                    Редактировать
                                </a>

                                <form method="POST"
                                      action="{{ route('admin.locations.destroy', $loc) }}"
                                      onsubmit="return confirm('Удалить локацию и все её фото?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold border border-red-200 bg-white text-red-700 hover:bg-red-50">
                                        Удалить
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="p-4">
                {{ $locations->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
