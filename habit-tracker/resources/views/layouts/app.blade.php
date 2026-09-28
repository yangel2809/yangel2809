<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#111827">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('pwa/icon-32.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('pwa/apple-touch-icon.png') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Hábitos">
        <meta name="sw-url" content="{{ asset('sw.js') }}">
        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{ $head ?? '' }}
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900">
        @php
            $tabs = [
                ['today', 'Hoy', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', ['today']],
                ['priorities.edit', 'Mañana', 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5', ['priorities.*']],
                ['progress', 'Progreso', 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z', ['progress', 'report']],
                ['habits.index', 'Hábitos', 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z', ['habits.*']],
            ];
            $accountIcon = 'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z';
            $accountActive = request()->routeIs('profile.*');
            // Móvil: siempre una columna estrecha. Escritorio: según la pantalla.
            $container = 'max-w-lg mx-auto px-4 lg:px-8 '.($width === 'wide' ? 'lg:max-w-6xl' : 'lg:max-w-2xl');
        @endphp

        {{-- Barra lateral (solo escritorio) --}}
        <aside class="hidden lg:flex fixed inset-y-0 left-0 z-30 w-60 flex-col bg-white border-r border-gray-200">
            <a href="{{ route('today') }}" class="flex items-center gap-3 px-6 h-20 shrink-0">
                <img src="{{ asset('pwa/icon-192.png') }}" alt="" class="w-9 h-9 rounded-xl">
                <span class="text-lg font-bold tracking-tight">{{ config('app.name') }}</span>
            </a>
            <nav class="flex-1 px-3 space-y-1" aria-label="Principal">
                @foreach ($tabs as [$route, $label, $icon, $patterns])
                    @php $active = request()->routeIs(...$patterns); @endphp
                    <a href="{{ route($route) }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition', 'bg-gray-900 text-white' => $active, 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! $active])
                       @if ($active) aria-current="page" @endif>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
            <div class="p-3 border-t border-gray-100">
                <a href="{{ route('profile.edit') }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 transition', 'bg-gray-100' => $accountActive, 'hover:bg-gray-100' => ! $accountActive])>
                    <svg class="w-8 h-8 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $accountIcon }}"/></svg>
                    <span class="min-w-0 leading-tight">
                        <span class="block text-sm font-medium truncate">{{ auth()->user()->name }}</span>
                        <span class="block text-xs text-gray-500">Cuenta</span>
                    </span>
                </a>
            </div>
        </aside>

        <div class="min-h-screen pb-[calc(4.5rem+env(safe-area-inset-bottom))] lg:pb-0 lg:pl-60">
            <header class="sticky top-0 z-20 bg-gray-100/90 backdrop-blur pt-[env(safe-area-inset-top)]">
                <div class="{{ $container }} h-14 lg:h-20 flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">{{ $header ?? '' }}</div>
                    <a href="{{ route('profile.edit') }}" class="lg:hidden shrink-0 p-2 -mr-2 text-gray-500 hover:text-gray-900" aria-label="Cuenta">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $accountIcon }}"/></svg>
                    </a>
                </div>
            </header>

            <main class="{{ $container }} pb-6 lg:pb-12">
                @if (session('status'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)" x-transition
                         class="mb-4 rounded-xl bg-gray-900 text-white text-sm px-4 py-3">{{ session('status') }}</div>
                @endif
                {{ $slot }}
            </main>
        </div>

        {{-- Barra inferior (solo móvil) --}}
        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white border-t border-gray-200 pb-[env(safe-area-inset-bottom)]">
            <div class="max-w-lg mx-auto grid grid-cols-4">
                @foreach ($tabs as [$route, $label, $icon, $patterns])
                    @php $active = request()->routeIs(...$patterns); @endphp
                    <a href="{{ route($route) }}" @class(['flex flex-col items-center gap-0.5 py-2.5 text-xs font-medium', 'text-gray-900' => $active, 'text-gray-400' => ! $active])>
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="{{ $active ? 2.2 : 1.8 }}" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </nav>
    </body>
</html>
