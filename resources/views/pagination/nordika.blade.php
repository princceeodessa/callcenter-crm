{{-- Страницы списка в стиле Nordika: «1–25 из 3 466» и кнопки страниц (вместо английского «Showing … results»). --}}
@if ($paginator->hasPages())
    <nav class="nd-pager" aria-label="Страницы">
        <span class="nd-pager-info">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} из {{ number_format($paginator->total(), 0, ',', ' ') }}</span>
        <span class="nd-pager-pages">
            @if ($paginator->onFirstPage())
                <span class="nd-pg nd-off" aria-hidden="true">‹</span>
            @else
                <a class="nd-pg" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Назад">‹</a>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="nd-pg nd-off">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="nd-pg nd-on" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="nd-pg" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a class="nd-pg" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Дальше">›</a>
            @else
                <span class="nd-pg nd-off" aria-hidden="true">›</span>
            @endif
        </span>
    </nav>
@endif
