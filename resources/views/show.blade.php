@extends('activitylog-ui::layout')

@section('title', __('activitylog-ui::ui.activity').' #'.$activity->getKey())

@section('content')
    @php
        $event = $activity->event;
        $mode = $changes['mode'];
        $rows = $changes['rows'];
        $unchangedCount = collect($rows)->where('changed', false)->count();
        $changedCount = count($rows) - $unchangedCount;
        $changesTitle = match (true) {
            $mode === 'diff' => __('activitylog-ui::ui.changes_updated'),
            $event === 'created' => __('activitylog-ui::ui.changes_created'),
            $event === 'deleted' => __('activitylog-ui::ui.changes_deleted'),
            default => __('activitylog-ui::ui.changes_values'),
        };
        $createdAt = $activity->created_at;
        $dateFormat = config('activitylog-ui.date_format', 'd/m/Y H:i:s');
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ $backUrl }}" class="alu-btn-secondary" title="{{ __('activitylog-ui::ui.back') }}">
                @include('activitylog-ui::partials.icon', ['name' => 'arrow-left'])
                <span class="hidden sm:inline">{{ __('activitylog-ui::ui.back') }}</span>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('activitylog-ui::ui.activity') }} #{{ $activity->getKey() }}</h1>
                    @include('activitylog-ui::partials.event-badge', ['event' => $event])
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $activity->description }}</p>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Summary --}}
        <div class="space-y-6 lg:col-span-1">
            <section class="alu-card">
                <h2 class="alu-card-title">
                    @include('activitylog-ui::partials.icon', ['name' => 'info', 'class' => 'h-4 w-4 text-indigo-500'])
                    {{ __('activitylog-ui::ui.event_information') }}
                </h2>
                <dl class="divide-y divide-slate-100 px-5 dark:divide-slate-800">
                    <div class="alu-dl-row">
                        <dt>{{ __('activitylog-ui::ui.event') }}</dt>
                        <dd>@include('activitylog-ui::partials.event-badge', ['event' => $event])</dd>
                    </div>
                    <div class="alu-dl-row">
                        <dt>{{ __('activitylog-ui::ui.description') }}</dt>
                        <dd>{{ $activity->description }}</dd>
                    </div>
                    @if ($createdAt)
                        <div class="alu-dl-row">
                            <dt>{{ __('activitylog-ui::ui.date') }}</dt>
                            <dd>
                                {{ $createdAt->format($dateFormat) }}
                                <span class="block text-xs text-slate-400">{{ $createdAt->diffForHumans() }}</span>
                            </dd>
                        </div>
                    @endif
                    @if ($activity->log_name)
                        <div class="alu-dl-row">
                            <dt>{{ __('activitylog-ui::ui.log_name') }}</dt>
                            <dd><span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs dark:bg-slate-800">{{ $activity->log_name }}</span></dd>
                        </div>
                    @endif
                    @if ($activity->getAttribute('batch_uuid'))
                        <div class="alu-dl-row">
                            <dt>{{ __('activitylog-ui::ui.batch') }}</dt>
                            <dd>
                                <a href="{{ route('activitylog-ui.index', ['batch' => $activity->getAttribute('batch_uuid')]) }}" class="break-all font-mono text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-300">{{ $activity->getAttribute('batch_uuid') }}</a>
                                @if ($batchMates->isNotEmpty())
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($batchMates as $mate)
                                            <li>
                                                <a href="{{ route('activitylog-ui.show', ['activity' => $mate->getKey(), 'batch' => $activity->getAttribute('batch_uuid')]) }}" class="text-sm font-sans font-medium text-slate-700 hover:text-indigo-600 dark:text-slate-200 dark:hover:text-indigo-300">
                                                    {{ $subjectTypes->label($mate->subject_type) }} #{{ $mate->subject_id }}
                                                    <span class="font-normal text-slate-400">{{ $mate->event }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="alu-card">
                <h2 class="alu-card-title">
                    @include('activitylog-ui::partials.icon', ['name' => 'cube', 'class' => 'h-4 w-4 text-violet-500'])
                    {{ __('activitylog-ui::ui.context') }}
                </h2>
                <dl class="divide-y divide-slate-100 px-5 dark:divide-slate-800">
                    <div class="alu-dl-row">
                        <dt>{{ __('activitylog-ui::ui.performed_by') }}</dt>
                        <dd class="flex items-center gap-2">
                            @include('activitylog-ui::partials.avatar', ['label' => $causerLabel, 'system' => $activity->causer_id === null])
                            <span class="font-medium">{{ $causerLabel }}</span>
                        </dd>
                    </div>
                    <div class="alu-dl-row">
                        <dt>{{ __('activitylog-ui::ui.subject') }}</dt>
                        <dd>
                            @if ($activity->subject_type)
                                <span class="font-medium" title="{{ $subjectTypes->title($activity->subject_type) }}">{{ $subjectTypes->label($activity->subject_type) }}</span>
                                <span class="text-slate-400">#{{ $activity->subject_id }}</span>
                                <span class="block break-all font-mono text-[11px] text-slate-400">{{ $subjectTypes->className($activity->subject_type) }}</span>
                            @else
                                <span class="text-slate-400">&mdash;</span>
                            @endif
                        </dd>
                    </div>
                </dl>
                @if ($subjectHistoryUrl || $relationsHistoryUrl)
                    <div class="space-y-2 border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                        @if ($relationsHistoryUrl)
                            <a href="{{ $relationsHistoryUrl }}" class="flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                                @include('activitylog-ui::partials.icon', ['name' => 'cube'])
                                {{ __('activitylog-ui::ui.history_with_relations') }}
                            </a>
                        @endif
                        @if ($subjectHistoryUrl)
                            <a href="{{ $subjectHistoryUrl }}" class="flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                                @include('activitylog-ui::partials.icon', ['name' => 'history'])
                                {{ __('activitylog-ui::ui.subject_history') }}
                            </a>
                        @endif
                    </div>
                @endif
            </section>
        </div>

        {{-- Changes --}}
        <div class="space-y-6 lg:col-span-2">
            <section class="alu-card overflow-hidden" x-data="{ onlyChanged: false }">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                            @include('activitylog-ui::partials.icon', ['name' => 'table', 'class' => 'h-4 w-4 text-emerald-500'])
                            {{ $changesTitle }}
                        </h2>
                        @if ($mode !== 'empty')
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                @if ($mode === 'diff')
                                    {{ trans_choice('activitylog-ui::ui.fields_changed', $changedCount, ['count' => $changedCount]) }}
                                @else
                                    {{ trans_choice('activitylog-ui::ui.fields', count($rows), ['count' => count($rows)]) }}
                                @endif
                            </p>
                        @endif
                    </div>
                    @if ($mode === 'diff' && $unchangedCount > 0)
                        <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                            <input type="checkbox" x-model="onlyChanged" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800">
                            {{ __('activitylog-ui::ui.only_changed') }}
                        </label>
                    @endif
                </div>

                @if ($mode === 'empty')
                    <div class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ __('activitylog-ui::ui.no_changes') }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                                    <th class="w-1/4 px-5 py-3">{{ __('activitylog-ui::ui.field') }}</th>
                                    @if ($mode === 'diff')
                                        <th class="w-3/8 px-5 py-3">{{ __('activitylog-ui::ui.old_value') }}</th>
                                        <th class="w-3/8 px-5 py-3">{{ __('activitylog-ui::ui.new_value') }}</th>
                                    @else
                                        <th class="px-5 py-3">{{ __('activitylog-ui::ui.value') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($rows as $row)
                                    <tr @if ($mode === 'diff' && ! $row['changed']) x-show="! onlyChanged" @endif class="align-top">
                                        <td class="px-5 py-3">
                                            <span class="break-all font-mono text-xs font-medium text-slate-800 dark:text-slate-200">{{ $row['field'] }}</span>
                                        </td>
                                        @if ($mode === 'diff')
                                            <td @class([
                                                'px-5 py-3 text-slate-700 dark:text-slate-300',
                                                'bg-rose-50/70 dark:bg-rose-500/5' => $row['changed'],
                                            ])>
                                                @if ($row['changed'])
                                                    <div class="border-l-2 border-rose-400 pl-3 dark:border-rose-500/60">
                                                        @include('activitylog-ui::partials.value', ['value' => $row['old'], 'hidden' => $row['hidden']])
                                                    </div>
                                                @else
                                                    @include('activitylog-ui::partials.value', ['value' => $row['old'], 'hidden' => $row['hidden']])
                                                @endif
                                            </td>
                                            <td @class([
                                                'px-5 py-3 text-slate-700 dark:text-slate-300',
                                                'bg-emerald-50/70 dark:bg-emerald-500/5' => $row['changed'],
                                            ])>
                                                @if ($row['changed'])
                                                    <div class="border-l-2 border-emerald-400 pl-3 dark:border-emerald-500/60">
                                                        @include('activitylog-ui::partials.value', ['value' => $row['new'], 'hidden' => $row['hidden']])
                                                    </div>
                                                @else
                                                    @include('activitylog-ui::partials.value', ['value' => $row['new'], 'hidden' => $row['hidden']])
                                                @endif
                                            </td>
                                        @else
                                            <td class="px-5 py-3 text-slate-700 dark:text-slate-300">
                                                @include('activitylog-ui::partials.value', ['value' => $row['new'], 'hidden' => $row['hidden']])
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @if (! empty($extra))
                <section class="alu-card overflow-hidden">
                    <h2 class="alu-card-title border-b border-slate-200 dark:border-slate-800">
                        @include('activitylog-ui::partials.icon', ['name' => 'info', 'class' => 'h-4 w-4 text-amber-500'])
                        {{ __('activitylog-ui::ui.extra_properties') }}
                    </h2>
                    <table class="min-w-full table-fixed text-sm">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($extra as $key => $value)
                                <tr class="align-top">
                                    <td class="w-1/4 px-5 py-3"><span class="break-all font-mono text-xs font-medium text-slate-800 dark:text-slate-200">{{ $key }}</span></td>
                                    <td class="px-5 py-3 text-slate-700 dark:text-slate-300">
                                        @include('activitylog-ui::partials.value', ['value' => $value, 'hidden' => $changesResolver->isHidden((string) $key)])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif

            @if ($raw !== [])
                <details class="alu-card group overflow-hidden">
                    <summary class="flex cursor-pointer select-none items-center justify-between px-5 py-4 text-sm font-semibold text-slate-900 dark:text-white">
                        <span class="flex items-center gap-2">
                            @include('activitylog-ui::partials.icon', ['name' => 'code', 'class' => 'h-4 w-4 text-slate-400'])
                            {{ __('activitylog-ui::ui.raw_json') }}
                        </span>
                        @include('activitylog-ui::partials.icon', ['name' => 'chevron-down', 'class' => 'h-4 w-4 text-slate-400 transition group-open:rotate-180'])
                    </summary>
                    <div class="space-y-4 border-t border-slate-200 p-5 dark:border-slate-800">
                        @foreach ($raw as $column => $json)
                            <div>
                                <p class="mb-1.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $column }}</p>
                                <pre class="max-h-96 overflow-auto rounded-lg bg-slate-900 p-4 font-mono text-xs leading-relaxed text-emerald-300 dark:bg-slate-950">{{ $json }}</pre>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </div>
@endsection
