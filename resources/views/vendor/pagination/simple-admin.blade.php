@if ($paginator->hasPages())
    <nav class="pagination-shell" role="navigation" aria-label="Pagination">
        <div class="pagination-summary">
            <strong>{{ number_format($paginator->firstItem() ?? 0) }}-{{ number_format($paginator->lastItem() ?? $paginator->count()) }}</strong>
        </div>

        <div class="pagination-nav">
            @if ($paginator->onFirstPage())
                <span class="pagination-link is-disabled" aria-disabled="true">{{ __('pagination.previous') }}</span>
            @else
                <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('pagination.previous') }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('pagination.next') }}</a>
            @else
                <span class="pagination-link is-disabled" aria-disabled="true">{{ __('pagination.next') }}</span>
            @endif
        </div>
    </nav>
@endif
