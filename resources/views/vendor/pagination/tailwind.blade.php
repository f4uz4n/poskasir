@if ($paginator->hasPages() || $paginator->total() > 0)
    <nav role="navigation" aria-label="Navigasi halaman" class="pagination-nav">
        {{-- Mobile --}}
        <div class="flex items-center justify-between gap-3 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="pagination-btn pagination-btn-disabled" aria-disabled="true">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagination-btn">Sebelumnya</a>
            @endif

            <span class="pagination-meta text-center">
                Hal. <strong>{{ $paginator->currentPage() }}</strong> / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagination-btn">Berikutnya</a>
            @else
                <span class="pagination-btn pagination-btn-disabled" aria-disabled="true">Berikutnya</span>
            @endif
        </div>

        {{-- Desktop --}}
        <div class="hidden sm:flex sm:items-center sm:justify-between sm:gap-4">
            <p class="pagination-meta">
                @if ($paginator->firstItem())
                    Menampilkan
                    <strong>{{ number_format($paginator->firstItem(), 0, ',', '.') }}</strong>
                    –
                    <strong>{{ number_format($paginator->lastItem(), 0, ',', '.') }}</strong>
                    dari
                    <strong>{{ number_format($paginator->total(), 0, ',', '.') }}</strong>
                    data
                @else
                    Tidak ada data
                @endif
            </p>

            @if ($paginator->hasPages())
                <ul class="pagination-list">
                    <li>
                        @if ($paginator->onFirstPage())
                            <span class="pagination-page pagination-page-disabled" aria-disabled="true" aria-label="Sebelumnya">
                                <svg class="pagination-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        @else
                            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagination-page" aria-label="Sebelumnya">
                                <svg class="pagination-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        @endif
                    </li>

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <li><span class="pagination-ellipsis" aria-hidden="true">…</span></li>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                <li>
                                    @if ($page == $paginator->currentPage())
                                        <span class="pagination-page pagination-page-active" aria-current="page">{{ $page }}</span>
                                    @else
                                        <a href="{{ $url }}" class="pagination-page" aria-label="Ke halaman {{ $page }}">{{ $page }}</a>
                                    @endif
                                </li>
                            @endforeach
                        @endif
                    @endforeach

                    <li>
                        @if ($paginator->hasMorePages())
                            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagination-page" aria-label="Berikutnya">
                                <svg class="pagination-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        @else
                            <span class="pagination-page pagination-page-disabled" aria-disabled="true" aria-label="Berikutnya">
                                <svg class="pagination-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        @endif
                    </li>
                </ul>
            @endif
        </div>
    </nav>
@endif
