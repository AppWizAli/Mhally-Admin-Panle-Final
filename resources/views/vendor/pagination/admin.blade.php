@if ($paginator->hasPages())
    <nav class="pagination-shell" role="navigation" aria-label="Pagination">
        <div class="pagination-summary">
            <strong>{{ number_format($paginator->firstItem() ?? 0) }}-{{ number_format($paginator->lastItem() ?? $paginator->count()) }}</strong>
            <span>/ {{ number_format($paginator->total()) }}</span>
        </div>

        <div class="pagination-nav">
            @if ($paginator->onFirstPage())
                <span class="pagination-link is-disabled" aria-disabled="true">{{ __('pagination.previous') }}</span>
            @else
                <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('pagination.previous') }}</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-separator" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-link is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pagination-link" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('pagination.next') }}</a>
            @else
                <span class="pagination-link is-disabled" aria-disabled="true">{{ __('pagination.next') }}</span>
            @endif
        </div>
    </nav>
@endif
