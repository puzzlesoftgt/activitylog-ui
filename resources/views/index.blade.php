@extends('activitylog-ui::layout')

@section('title', __('activitylog-ui::ui.title'))

@section('content')
    @php
        $isLengthAware = $activities instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    @endphp

    <div x-data="{ filtersOpen: false }">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('activitylog-ui::ui.title') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('activitylog-ui::ui.subtitle') }}</p>
            </div>
            <button type="button" x-on:click="filtersOpen = true" class="alu-btn-secondary lg:hidden">
                @include('activitylog-ui::partials.icon', ['name' => 'filter'])
                {{ __('activitylog-ui::ui.filters') }}
                @if ($activeFilters > 0)
                    <span class="rounded-full bg-indigo-600 px-1.5 text-[10px] font-semibold text-white">{{ $activeFilters }}</span>
                @endif
            </button>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[19rem_minmax(0,1fr)]">
            <div x-show="filtersOpen" x-cloak x-transition.opacity x-on:click="filtersOpen = false" class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"></div>

            <aside class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] -translate-x-full overflow-y-auto bg-slate-100 p-3 transition-transform duration-200 lg:static lg:z-auto lg:w-auto lg:max-w-none lg:translate-x-0 lg:overflow-visible lg:bg-transparent lg:p-0 dark:bg-slate-950 lg:dark:bg-transparent"
                   :class="{ 'translate-x-0!': filtersOpen }">
                @include('activitylog-ui::partials.filters')
            </aside>

            <section class="alu-card alu-results overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('activitylog-ui::ui.activities') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            @if ($isLengthAware)
                                {{ trans_choice('activitylog-ui::ui.found', $activities->total(), ['count' => number_format($activities->total())]) }}
                            @else
                                {{ __('activitylog-ui::ui.page', ['page' => $activities->currentPage()]) }}
                            @endif
                        </p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('activitylog-ui::ui.show') }}
                        <select name="per_page" form="activitylog-filters" x-on:change="ActivitylogUi.submitFilters()" class="alu-input w-auto py-1">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        {{ __('activitylog-ui::ui.per_page') }}
                    </label>
                </div>

                @if ($historyUrl)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-indigo-100 bg-indigo-50/60 px-5 py-3 text-sm dark:border-indigo-500/20 dark:bg-indigo-500/10">
                        <span class="flex items-center gap-2 text-indigo-800 dark:text-indigo-200">
                            @include('activitylog-ui::partials.icon', ['name' => 'history', 'class' => 'h-4 w-4'])
                            {{ __('activitylog-ui::ui.history_banner') }}
                        </span>
                        <a href="{{ $historyUrl }}" class="inline-flex items-center gap-1 font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-300 dark:hover:text-indigo-200">
                            {{ __('activitylog-ui::ui.history_with_relations') }}
                            @include('activitylog-ui::partials.icon', ['name' => 'arrow-right', 'class' => 'h-3.5 w-3.5'])
                        </a>
                    </div>
                @endif

                @if ($activities->isEmpty())
                    <div class="flex flex-col items-center justify-center px-6 py-20 text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                            @include('activitylog-ui::partials.icon', ['name' => 'inbox', 'class' => 'h-6 w-6'])
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">{{ __('activitylog-ui::ui.empty_title') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('activitylog-ui::ui.empty_text') }}</p>
                        @if ($activeFilters > 0)
                            <a href="{{ route('activitylog-ui.index') }}" class="alu-btn-secondary mt-4">{{ __('activitylog-ui::ui.clear_filters') }}</a>
                        @endif
                    </div>
                @else
                    @include('activitylog-ui::partials.activity-table')
                @endif
            </section>
        </div>
    </div>
@endsection
