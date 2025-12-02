@if ($paginator->hasPages())
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        {{-- Results Info --}}
        <div class="pagination-info text-muted small">
            @php
                $start = ($paginator->currentPage() - 1) * $paginator->perPage() + 1;
                $end = min($paginator->currentPage() * $paginator->perPage(), $paginator->total());
            @endphp
            <span class="d-none d-sm-inline">Showing</span>
            <strong class="text-dark">{{ number_format($start) }}</strong>
            <span class="d-none d-sm-inline">to</span>
            <strong class="text-dark">{{ number_format($end) }}</strong>
            <span class="d-none d-sm-inline">of</span>
            <strong class="text-dark">{{ number_format($paginator->total()) }}</strong>
            <span class="d-none d-sm-inline">results</span>
        </div>

        {{-- Pagination Controls --}}
        <nav aria-label="pagination" class="pagination-wrapper">
            <ul class="pagination mb-0">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                        <span class="page-link">
                            <i class="fas fa-chevron-left"></i>
                            <span class="d-none d-sm-inline ms-1">Previous</span>
                        </span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">
                            <i class="fas fa-chevron-left"></i>
                            <span class="d-none d-sm-inline ms-1">Previous</span>
                        </a>
                    </li>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true">
                            <span class="page-link">{{ $element }}</span>
                        </li>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page">
                                    <span class="page-link">{{ $page }}</span>
                                </li>
                            @else
                                <li class="page-item">
                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">
                            <span class="d-none d-sm-inline me-1">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                        <span class="page-link">
                            <span class="d-none d-sm-inline me-1">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </li>
                @endif
            </ul>
        </nav>
    </div>
@endif
