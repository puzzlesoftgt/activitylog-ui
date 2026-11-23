@extends('activitylog-ui::layout')

@section('title', __('activitylog-ui::ui.history').' · '.$subjectTypes->label($subjectType).' #'.$subjectId)

@section('content')
    @php
        $isLengthAware = $activities instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    @endphp

    <div class="mb-6 flex flex-wrap items-center gap-4">
        <a href="{{ route('activitylog-ui.index', ['subject_type' => $subjectType, 'subject_id' => $subjectId]) }}" class="alu-btn-secondary" title="{{ __('activitylog-ui::ui.back') }}">
            @include('activitylog-ui::partials.icon', ['name' => 'arrow-left'])
            <span class="hidden sm:inline">{{ __('activitylog-ui::ui.back') }}</span>
        </a>
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ $subjectTypes->label($subjectType) }} <span class="text-slate-400">#{{ $subjectId }}</span>
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('activitylog-ui::ui.history_subtitle') }}
                <span class="font-mono text-xs text-slate-400">{{ $subjectTypes->className($subjectType) }}</span>
            </p>
        </div>
    </div>

    {{-- Filters --}}
    <form id="activitylog-filters"
          method="GET"
          action="{{ route('activitylog-ui.history') }}"
          x-data="{ date: @js($filters['date']) }"
          x-on:submit.prevent="ActivitylogUi.submitFilters()"
          class="alu-card mb-6 flex flex-wrap items-end gap-x-8 gap-y-4 px-5 py-4">
        <input type="hidden" name="subject_type" value="{{ $subjectType }}">
        <input type="hidden" name="subject_id" value="{{ $subjectId }}">

        <div>
            <label for="alu-relation" class="alu-label">{{ __('activitylog-ui::ui.relation') }}</label>
            <select id="alu-relation" name="relation" x-on:change="ActivitylogUi.submitFilters()" class="alu-input w-auto min-w-48">
                <option value="{{ \Puzzlesoft\ActivitylogUi\Relations\RelationResolver::SELF }}" @selected($relation === \Puzzlesoft\ActivitylogUi\Relations\RelationResolver::SELF)>{{ __('activitylog-ui::ui.this_record') }}</option>
                @foreach ($relations as $childType => $definition)
                    <option value="{{ $childType }}" title="{{ $definition['foreign_key'] }}" @selected($relation === $childType)>{{ $definition['label'] }}</option>
                @endforeach
            </select>
            @if ($relations === [])
                <p class="alu-hint max-w-sm">{{ __('activitylog-ui::ui.no_relations') }}</p>
            @endif
        </div>

        <div>
            <span class="alu-label">{{ __('activitylog-ui::ui.event_types') }}</span>
            <div class="flex flex-wrap gap-2">
                @foreach ($events as $event)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="events[]" value="{{ $event }}" class="peer sr-only" @checked(in_array($event, $filters['events'], true)) x-on:change="ActivitylogUi.submitFilters()">
                        <span class="alu-pill peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 dark:peer-checked:border-indigo-400 dark:peer-checked:bg-indigo-500/15 dark:peer-checked:text-indigo-300">
                            {{ \Illuminate\Support\Facades\Lang::has('activitylog-ui::ui.events.'.$event) ? __('activitylog-ui::ui.events.'.$event) : $event }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <span class="alu-label">{{ __('activitylog-ui::ui.date_range') }}</span>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($datePresets as $preset)
                    <label class="cursor-pointer">
                        <input type="radio" name="date" value="{{ $preset }}" class="peer sr-only" x-model="date" @checked($filters['date'] === $preset) x-on:change="date !== 'custom' && ActivitylogUi.submitFilters()">
                        <span class="alu-pill peer-checked:border-indigo-500 peer-checked:bg-indigo-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 dark:peer-checked:border-indigo-500">
                            {{ __('activitylog-ui::ui.dates.'.$preset) }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div x-show="date === 'custom'" @if ($filters['date'] !== 'custom') x-cloak @endif class="flex gap-2">
            <div>
                <label for="alu-from" class="alu-label">{{ __('activitylog-ui::ui.from') }}</label>
                <input id="alu-from" type="date" name="from" value="{{ $filters['from'] }}" x-on:change="ActivitylogUi.submitFilters()" class="alu-input">
            </div>
            <div>
                <label for="alu-to" class="alu-label">{{ __('activitylog-ui::ui.to') }}</label>
                <input id="alu-to" type="date" name="to" value="{{ $filters['to'] }}" x-on:change="ActivitylogUi.submitFilters()" class="alu-input">
            </div>
        </div>

        <noscript>
            <button type="submit" class="alu-btn-primary">{{ __('activitylog-ui::ui.apply') }}</button>
        </noscript>
    </form>

    {{-- Activities --}}
    <section x-data class="alu-card alu-results overflow-hidden">
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

        @if ($activities->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-20 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                    @include('activitylog-ui::partials.icon', ['name' => 'inbox', 'class' => 'h-6 w-6'])
                </span>
                <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">{{ __('activitylog-ui::ui.empty_title') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('activitylog-ui::ui.empty_text') }}</p>
            </div>
        @else
            @include('activitylog-ui::partials.activity-table', [
                'showQuery' => ['back' => 'history'] + request()->query(),
                'parent' => ['type' => $subjectType, 'id' => $subjectId],
                'deleted' => $deleted,
                'relationLabels' => collect($relations)->mapWithKeys(fn ($definition, $type) => [$type => $definition['label']])->all(),
            ])
        @endif
    </section>
@endsection
