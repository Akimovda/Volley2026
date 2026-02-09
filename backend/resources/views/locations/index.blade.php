<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Локации
            </h2>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 py-10">
        <div class="text-sm text-gray-600 mb-6">
            Доступные локации (фото, адрес, карта).
        </div>

        @if($locations->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 p-6 text-sm text-gray-500">
                Пока нет локаций.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($locations as $loc)
                    <x-location-card :location="$loc" />
                @endforeach
            </div>

            <div class="mt-8">
                {{ $locations->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
