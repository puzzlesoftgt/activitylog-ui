@php
    $isLengthAware = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $base = 'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm font-medium transition';
    $idle = 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800';
    $disabled = 'cursor-not-allowed text-slate-300 dark:text-slate-600';
@endphp

<nav role="navigation" aria-label="{{ __('activitylog-ui::ui.pagination') }}" class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-xs text-slate-500 dark:text-slate-400">
        @if ($isLengthAware && $paginator->firstItem())
            {{ __('activitylog-ui::ui.showing', ['first' => number_format($paginator->firstItem()), 'last' => number_format($paginator->lastItem()), 'total' => number_format($paginator->total())]) }}
        @else
            {{ __('activitylog-ui::ui.page', ['page' => $paginator->currentPage()]) }}
        @endif
    </p>

    <div class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="{{ $base }} {{ $disabled }}">@include('activitylog-ui::partials.icon', ['name' => 'chevron-left'])</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $base }} {{ $idle }}" title="{{ __('activitylog-ui::ui.previous') }}">@include('activitylog-ui::partials.icon', ['name' => 'chevron-left'])</a>
        @endif

        @if ($isLengthAware && isset($elements))
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $base }} text-slate-400">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="{{ $base }} bg-indigo-600 text-white">{{ number_format($page) }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $base }} hidden sm:inline-flex {{ $idle }}">{{ number_format($page) }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $base }} {{ $idle }}" title="{{ __('activitylog-ui::ui.next') }}">@include('activitylog-ui::partials.icon', ['name' => 'chevron-right'])</a>
        @else
            <span class="{{ $base }} {{ $disabled }}">@include('activitylog-ui::partials.icon', ['name' => 'chevron-right'])</span>
        @endif
    </div>
</nav>
