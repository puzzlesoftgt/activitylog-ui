<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', config('activitylog-ui.title', 'Activity Log')) &middot; {{ config('app.name') }}</title>
    <script>
        (function () {
            var theme = localStorage.getItem('activitylog-ui.theme') || 'auto';
            var dark = theme === 'dark' || (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    <link rel="stylesheet" href="{{ \Puzzlesoft\ActivitylogUi\Support\Assets::url('app.css') }}">
    <script defer src="{{ \Puzzlesoft\ActivitylogUi\Support\Assets::url('app.js') }}"></script>
</head>
<body class="min-h-full bg-slate-100 text-slate-700 antialiased dark:bg-slate-950 dark:text-slate-300">
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/85 backdrop-blur dark:border-slate-800 dark:bg-slate-900/85">
        <div class="mx-auto flex h-14 max-w-[1600px] items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('activitylog-ui.index') }}" class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-sm shadow-indigo-600/30">
                    @include('activitylog-ui::partials.icon', ['name' => 'logo', 'class' => 'h-5 w-5'])
                </span>
                <span class="leading-tight">
                    <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ config('activitylog-ui.title', 'Activity Log') }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ config('app.name') }}</span>
                </span>
            </a>

            <div class="flex items-center gap-3">
                <div x-data="themeSwitcher" class="flex items-center rounded-lg border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-800">
                    @foreach (['light' => 'sun', 'dark' => 'moon', 'auto' => 'monitor'] as $mode => $icon)
                        <button type="button"
                                x-on:click="set('{{ $mode }}')"
                                :class="theme === '{{ $mode }}' ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-700 dark:text-indigo-300' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                                class="rounded-md p-1.5 transition"
                                title="{{ __('activitylog-ui::ui.theme_'.$mode) }}">
                            @include('activitylog-ui::partials.icon', ['name' => $icon])
                            <span class="sr-only">{{ __('activitylog-ui::ui.theme_'.$mode) }}</span>
                        </button>
                    @endforeach
                </div>

                @if ($headerBackUrl = \Puzzlesoft\ActivitylogUi\ActivitylogUi::resolveBackUrl(request()))
                    <a href="{{ $headerBackUrl }}" class="flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">
                        @include('activitylog-ui::partials.icon', ['name' => 'arrow-left'])
                        <span class="hidden sm:inline">{{ __('activitylog-ui::ui.back_to_app') }}</span>
                    </a>
                @endif
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6">
        @yield('content')
    </main>
</body>
</html>
