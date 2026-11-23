@php($initial = $system ?? false ? null : mb_strtoupper(mb_substr(ltrim($label, '#'), 0, 1)))
<span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $initial ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300' : 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400' }}">
    @if ($initial)
        {{ $initial }}
    @else
        @include('activitylog-ui::partials.icon', ['name' => 'monitor', 'class' => 'h-3.5 w-3.5'])
    @endif
</span>
