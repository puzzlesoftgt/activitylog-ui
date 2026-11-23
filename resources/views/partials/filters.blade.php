@php
    $userList = collect($userOptions)
        ->map(fn ($label, $id) => ['value' => (string) $id, 'label' => (string) $label])
        ->values()
        ->prepend(['value' => \Puzzlesoft\ActivitylogUi\Support\UserOptionsResolver::SYSTEM, 'label' => __('activitylog-ui::ui.system')])
        ->prepend(['value' => '', 'label' => __('activitylog-ui::ui.all_users')])
        ->all();

    $selectedUserLabel = match (true) {
        $filters['causer'] === '' => __('activitylog-ui::ui.all_users'),
        $filters['causer'] === \Puzzlesoft\ActivitylogUi\Support\UserOptionsResolver::SYSTEM => __('activitylog-ui::ui.system'),
        default => $userOptions[$filters['causer']] ?? '#'.$filters['causer'],
    };
@endphp

<form id="activitylog-filters"
      method="GET"
      action="{{ route('activitylog-ui.index') }}"
      x-data="{ date: @js($filters['date']) }"
      x-on:submit.prevent="ActivitylogUi.submitFilters()"
      class="alu-card lg:sticky lg:top-20">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
            @include('activitylog-ui::partials.icon', ['name' => 'filter'])
            {{ __('activitylog-ui::ui.filters') }}
            @if ($activeFilters > 0)
                <span class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">{{ $activeFilters }}</span>
            @endif
        </h2>
        <div class="flex items-center gap-1">
            @if ($activeFilters > 0)
                <a href="{{ route('activitylog-ui.index') }}" class="alu-btn-ghost text-xs">
                    @include('activitylog-ui::partials.icon', ['name' => 'x', 'class' => 'h-3.5 w-3.5'])
                    {{ __('activitylog-ui::ui.clear') }}
                </a>
            @endif
            <button type="button" x-on:click="filtersOpen = false" class="alu-btn-ghost lg:hidden" title="{{ __('activitylog-ui::ui.close') }}">
                @include('activitylog-ui::partials.icon', ['name' => 'x'])
            </button>
        </div>
    </div>

    <div class="space-y-5 px-4 py-4">
        {{-- Search --}}
        <div>
            <label for="alu-search" class="alu-label">{{ __('activitylog-ui::ui.search') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    @include('activitylog-ui::partials.icon', ['name' => 'search'])
                </span>
                <input id="alu-search"
                       type="search"
                       name="search"
                       value="{{ $filters['search'] }}"
                       placeholder="{{ __('activitylog-ui::ui.search_placeholder') }}"
                       x-on:input.debounce.500ms="ActivitylogUi.submitFilters()"
                       class="alu-input pl-9">
            </div>
            <p class="alu-hint">{{ __('activitylog-ui::ui.search_hint') }}</p>
        </div>

        {{-- Subject --}}
        <div class="grid grid-cols-[minmax(0,1fr)_6.5rem] gap-2">
            <div>
                <label for="alu-subject-type" class="alu-label">{{ __('activitylog-ui::ui.subject_type') }}</label>
                <select id="alu-subject-type" name="subject_type" x-on:change="ActivitylogUi.submitFilters()" class="alu-input">
                    <option value="">{{ __('activitylog-ui::ui.all_types') }}</option>
                    @foreach ($subjectTypeOptions as $type => $option)
                        <option value="{{ $type }}" title="{{ $option['title'] }}" @selected($filters['subject_type'] === (string) $type)>{{ $option['label'] }}</option>
                    @endforeach
                    @if ($filters['subject_type'] !== '' && ! array_key_exists($filters['subject_type'], $subjectTypeOptions))
                        <option value="{{ $filters['subject_type'] }}" selected>{{ $subjectTypes->label($filters['subject_type']) }}</option>
                    @endif
                </select>
            </div>
            <div>
                <label for="alu-subject-id" class="alu-label">{{ __('activitylog-ui::ui.subject_id') }}</label>
                <input id="alu-subject-id"
                       type="text"
                       inputmode="numeric"
                       name="subject_id"
                       value="{{ $filters['subject_id'] }}"
                       placeholder="ID"
                       x-on:input.debounce.600ms="ActivitylogUi.submitFilters()"
                       class="alu-input">
            </div>
        </div>

        {{-- User / causer --}}
        <div>
            <span class="alu-label">{{ __('activitylog-ui::ui.user') }}</span>
            <div x-data="userSelect({ options: @js($userList), selected: @js($filters['causer']) })"
                 x-on:click.outside="open = false"
                 x-on:keydown.escape.stop="open = false"
                 class="relative">
                <input type="hidden" name="causer" value="{{ $filters['causer'] }}" :value="selected">
                <button type="button" x-on:click="toggle()" class="alu-input flex items-center justify-between gap-2 text-left">
                    <span class="truncate" x-text="selectedLabel">{{ $selectedUserLabel }}</span>
                    @include('activitylog-ui::partials.icon', ['name' => 'chevron-down', 'class' => 'h-4 w-4 shrink-0 text-slate-400'])
                </button>
                <div x-show="open" x-cloak x-transition.origin.top class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
                    <div class="border-b border-slate-100 p-2 dark:border-slate-700">
                        <input type="text"
                               x-ref="search"
                               x-model="query"
                               x-on:keydown.enter.prevent="chooseFirst()"
                               placeholder="{{ __('activitylog-ui::ui.search_user') }}"
                               class="alu-input">
                    </div>
                    <ul class="max-h-64 overflow-y-auto py-1">
                        <template x-for="option in filtered" :key="option.value">
                            <li>
                                <button type="button"
                                        x-on:click="choose(option.value)"
                                        :class="option.value === selected ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' : 'hover:bg-slate-50 dark:hover:bg-slate-700/60'"
                                        class="block w-full truncate px-3 py-1.5 text-left text-sm"
                                        x-text="option.label"></button>
                            </li>
                        </template>
                        <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-slate-500">{{ __('activitylog-ui::ui.no_results') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Events --}}
        <div>
            <span class="alu-label">{{ __('activitylog-ui::ui.event_types') }}</span>
            <div class="flex flex-wrap gap-2">
                @foreach ($events as $event)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="events[]" value="{{ $event }}" class="peer sr-only" @checked(in_array($event, $filters['events'], true)) x-on:change="ActivitylogUi.submitFilters()">
                        <span class="alu-pill peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 dark:peer-checked:border-indigo-400 dark:peer-checked:bg-indigo-500/15 dark:peer-checked:text-indigo-300">
                            <span @class([
                                'h-1.5 w-1.5 rounded-full',
                                'bg-emerald-500' => $event === 'created',
                                'bg-sky-500' => $event === 'updated',
                                'bg-rose-500' => $event === 'deleted',
                                'bg-amber-500' => $event === 'restored',
                                'bg-slate-400' => ! in_array($event, ['created', 'updated', 'deleted', 'restored'], true),
                            ])></span>
                            {{ \Illuminate\Support\Facades\Lang::has('activitylog-ui::ui.events.'.$event) ? __('activitylog-ui::ui.events.'.$event) : $event }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Log name --}}
        @if (count($logNames) > 1)
            <div>
                <label for="alu-log-name" class="alu-label">{{ __('activitylog-ui::ui.log_name') }}</label>
                <select id="alu-log-name" name="log_name" x-on:change="ActivitylogUi.submitFilters()" class="alu-input">
                    <option value="">{{ __('activitylog-ui::ui.all_logs') }}</option>
                    @foreach ($logNames as $logName)
                        <option value="{{ $logName }}" @selected($filters['log_name'] === $logName)>{{ $logName }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Date range --}}
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
            <div x-show="date === 'custom'" @if ($filters['date'] !== 'custom') x-cloak @endif class="mt-3 grid grid-cols-2 gap-2">
                <div>
                    <label for="alu-from" class="alu-label text-xs">{{ __('activitylog-ui::ui.from') }}</label>
                    <input id="alu-from" type="date" name="from" value="{{ $filters['from'] }}" x-on:change="ActivitylogUi.submitFilters()" class="alu-input">
                </div>
                <div>
                    <label for="alu-to" class="alu-label text-xs">{{ __('activitylog-ui::ui.to') }}</label>
                    <input id="alu-to" type="date" name="to" value="{{ $filters['to'] }}" x-on:change="ActivitylogUi.submitFilters()" class="alu-input">
                </div>
            </div>
        </div>
    </div>

    <noscript>
        <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
            <button type="submit" class="alu-btn-primary w-full justify-center">{{ __('activitylog-ui::ui.apply') }}</button>
        </div>
    </noscript>
</form>
