@if ($paginator->hasPages())
    @php
        // Прыжок через N страниц — полезно при большом числе страниц (см. .pagination-scroll ниже),
        // когда пролистывать по одной через скроллируемый ряд неудобно.
        $jumpStep = 10;
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $jumpBackPage = $currentPage - $jumpStep;
        $jumpForwardPage = $currentPage + $jumpStep;
    @endphp
    <div class="ramka text-center">
        <div class="pagination-scroll">
            {{-- Previous Page Link
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary disabled">Назад</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary" rel="prev">Назад</a>
            @endif
 --}}
            @if ($jumpBackPage >= 1)
                <a href="{{ $paginator->url($jumpBackPage) }}" class="btn btn-secondary" title="{{ __('ui.pagination_jump_back', ['n' => $jumpStep]) }}">« {{ $jumpStep }}</a>
            @endif

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
                            <span class="btn active pagination-scroll-active">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="btn btn-secondary">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($jumpForwardPage <= $lastPage)
                <a href="{{ $paginator->url($jumpForwardPage) }}" class="btn btn-secondary" title="{{ __('ui.pagination_jump_forward', ['n' => $jumpStep]) }}">{{ $jumpStep }} »</a>
            @endif

            {{-- Next Page Link
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary" rel="next">Вперед</a>
            @else
                <span class="btn btn-secondary disabled">Вперед</span>
            @endif
			 --}}
        </div>
    </div>
    <script>
    (function () {
        var container = document.currentScript.previousElementSibling;
        var active = container ? container.querySelector('.pagination-scroll-active') : null;
        if (active && active.scrollIntoView) {
            active.scrollIntoView({ inline: 'center', block: 'nearest' });
        }
    })();
    </script>
@endif
