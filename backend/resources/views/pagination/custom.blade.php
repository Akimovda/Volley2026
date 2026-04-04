@if ($paginator->hasPages())
    <div class="ramka text-center">
        <div class="flex gap-2 justify-center">
            {{-- Previous Page Link
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary disabled">Назад</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary" rel="prev">Назад</a>
            @endif
 --}}
            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span class="btn btn-secondary disabled">{{ $element }}</span>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn active">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="btn btn-secondary">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary" rel="next">Вперед</a>
            @else
                <span class="btn btn-secondary disabled">Вперед</span>
            @endif
			 --}}
        </div>
    </div>
@endif