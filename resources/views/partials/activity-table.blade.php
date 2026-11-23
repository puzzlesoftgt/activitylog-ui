{{--
    Shared activities table.
    Optional: $showQuery (query string for the detail links), $parent (['type' => ..., 'id' => ...])
    and $deleted ([child subject_type => [id => index]]) to mark the record itself and deleted children.
--}}
@php
    $showQuery = $showQuery ?? request()->query();
    $parent = $parent ?? null;
    $deleted = $deleted ?? [];
    $relationLabels = $relationLabels ?? [];
    $previousBatch = null;
@endphp

<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead>
            <tr class="border-b border-slate-200 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                <th class="px-5 py-3">{{ __('activitylog-ui::ui.event') }}</th>
                <th class="px-5 py-3">{{ __('activitylog-ui::ui.subject') }}</th>
                <th class="px-5 py-3">{{ __('activitylog-ui::ui.description') }}</th>
                <th class="px-5 py-3">{{ __('activitylog-ui::ui.user') }}</th>
                <th class="px-5 py-3">{{ __('activitylog-ui::ui.date') }}</th>
                <th class="px-5 py-3"><span class="sr-only">{{ __('activitylog-ui::ui.actions') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($activities as $activity)
                @php
                    $showUrl = route('activitylog-ui.show', ['activity' => $activity->getKey()] + $showQuery);
                    $causer = $users->label($activity->causer_type, $activity->causer_id);
                    $createdAt = $activity->created_at;
                    $isParent = $parent && $activity->subject_type === $parent['type'] && (string) $activity->subject_id === (string) $parent['id'];
                    $isDeleted = isset($deleted[$activity->subject_type][(string) $activity->subject_id]);
                    $subjectLabel = $relationLabels[$activity->subject_type] ?? $subjectTypes->label($activity->subject_type);
                    $batch = $activity->getAttribute('batch_uuid');
                    $grouped = $batch && $batch === ($previousBatch ?? null);
                    $previousBatch = $batch;
                @endphp
                <tr class="group cursor-pointer transition hover:bg-slate-50 dark:hover:bg-slate-800/50 {{ $batch ? 'border-l-2 border-l-indigo-400 dark:border-l-indigo-500' : '' }}" x-on:click="if (! $event.target.closest('a')) window.location = @js($showUrl)">
                    <td class="whitespace-nowrap px-5 py-3">
                        @include('activitylog-ui::partials.event-badge', ['event' => $activity->event])
                    </td>
                    <td class="whitespace-nowrap px-5 py-3">
                        @if ($activity->subject_type)
                            <span class="font-medium text-slate-900 dark:text-white" title="{{ $subjectTypes->title($activity->subject_type) }}">{{ $subjectLabel }}</span>
                            <span class="text-slate-400">#{{ $activity->subject_id }}</span>
                            @if ($batch)
                                <a href="{{ route('activitylog-ui.index', ['batch' => $batch]) }}" class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-indigo-600 hover:text-indigo-500 dark:text-indigo-300" title="{{ $batch }}">{{ $grouped ? __('activitylog-ui::ui.same_batch') : __('activitylog-ui::ui.view_batch') }}</a>
                            @endif
                            @if ($isParent)
                                <span class="ml-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">{{ __('activitylog-ui::ui.this_record') }}</span>
                            @elseif ($isDeleted)
                                <span class="ml-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-600 dark:bg-rose-500/15 dark:text-rose-300" title="{{ __('activitylog-ui::ui.deleted_record_hint') }}">{{ __('activitylog-ui::ui.deleted_record') }}</span>
                            @endif
                        @else
                            <span class="text-slate-400">&mdash;</span>
                        @endif
                    </td>
                    <td class="max-w-xs px-5 py-3">
                        <span class="line-clamp-2 text-slate-600 dark:text-slate-300">{{ $activity->description }}</span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-3">
                        <div class="flex items-center gap-2">
                            @include('activitylog-ui::partials.avatar', ['label' => $causer, 'system' => $activity->causer_id === null])
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $causer }}</span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-5 py-3">
                        @if ($createdAt)
                            <span class="block text-slate-700 dark:text-slate-200">{{ $createdAt->format(config('activitylog-ui.date_format', 'd/m/Y H:i:s')) }}</span>
                            <span class="block text-xs text-slate-400">{{ $createdAt->diffForHumans() }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-5 py-3 text-right">
                        <a href="{{ $showUrl }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                            {{ __('activitylog-ui::ui.view_details') }}
                            @include('activitylog-ui::partials.icon', ['name' => 'chevron-right', 'class' => 'h-3.5 w-3.5 transition group-hover:translate-x-0.5'])
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($activities->hasPages())
    <div class="border-t border-slate-200 px-5 py-3 dark:border-slate-800">
        {{ $activities->links('activitylog-ui::partials.pagination') }}
    </div>
@endif
