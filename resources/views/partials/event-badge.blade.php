@php
    $classes = match ($event) {
        'created' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30',
        'updated' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-500/30',
        'deleted' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/30',
        'restored' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30',
        default => 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-500/10 dark:text-slate-400 dark:ring-slate-500/30',
    };
    $dot = match ($event) {
        'created' => 'bg-emerald-500',
        'updated' => 'bg-sky-500',
        'deleted' => 'bg-rose-500',
        'restored' => 'bg-amber-500',
        default => 'bg-slate-400',
    };
    $eventLabel = match (true) {
        ! $event => __('activitylog-ui::ui.no_event'),
        \Illuminate\Support\Facades\Lang::has('activitylog-ui::ui.events.'.$event) => __('activitylog-ui::ui.events.'.$event),
        default => $event,
    };
@endphp
<span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $classes }}">
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
    {{ $eventLabel }}
</span>
