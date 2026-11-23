@php($formatted = \Puzzlesoft\ActivitylogUi\Support\ValueFormatter::format($value))

@if ($hidden ?? false)
    <span class="font-mono text-slate-400" title="{{ __('activitylog-ui::ui.hidden_value') }}">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
@else
    @switch($formatted['type'])
        @case('null')
            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs italic text-slate-400 dark:bg-slate-800 dark:text-slate-500">null</span>
            @break
        @case('empty')
            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs italic text-slate-400 dark:bg-slate-800 dark:text-slate-500">{{ __('activitylog-ui::ui.empty_string') }}</span>
            @break
        @case('bool')
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $formatted['raw'] === 'true' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}" title="{{ $formatted['raw'] }}">{{ $formatted['display'] }}</span>
            @break
        @case('number')
            <span class="font-mono tabular-nums">{{ $formatted['display'] }}</span>
            @break
        @case('date')
            <span class="inline-flex items-center gap-1.5" title="{{ $formatted['raw'] }}">
                @include('activitylog-ui::partials.icon', ['name' => 'clock', 'class' => 'h-3.5 w-3.5 text-slate-400'])
                {{ $formatted['display'] }}
            </span>
            @break
        @case('json')
            <details class="group/json">
                <summary class="cursor-pointer select-none font-mono text-xs text-indigo-600 dark:text-indigo-400">
                    {{ is_array($value) ? (array_is_list($value) ? '[ '.count($value).' ]' : '{ '.count($value).' }') : '{ }' }}
                </summary>
                <pre class="mt-2 max-h-72 overflow-auto rounded-md bg-slate-50 p-2 font-mono text-xs leading-relaxed text-slate-700 dark:bg-slate-950 dark:text-slate-300">{{ $formatted['display'] }}</pre>
            </details>
            @break
        @default
            <span class="whitespace-pre-wrap break-words">{{ $formatted['display'] }}</span>
    @endswitch
@endif
