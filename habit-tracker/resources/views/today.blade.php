@php
    use App\Support\LocalDate;
    $payload = [
        'date' => $day->format('Y-m-d'),
        'habits' => $board['habits'],
        'priorities' => $board['priorities'],
        'urls' => [
            'log' => route('logs.update', '__ID__'),
            'priority' => route('priorities.mark', '__ID__'),
        ],
    ];
@endphp

<x-app-layout :title="$isToday ? 'Hoy' : LocalDate::human($day)" width="wide">
    <x-slot name="header">
        <div class="flex items-center gap-1">
            @if ($prev)
                <a href="{{ route('today', ['fecha' => $prev]) }}" class="p-2 -ml-2 text-gray-500 hover:text-gray-900" aria-label="Día anterior">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                </a>
            @endif
            <div class="min-w-0 leading-tight">
                <h1 class="text-xl font-bold">{{ $isToday ? 'Hoy' : ucfirst($day->locale('es')->isoFormat('dddd')) }}</h1>
                <p class="text-xs text-gray-500 truncate">{{ LocalDate::human($day) }}</p>
            </div>
            @if (! $isToday)
                <a href="{{ $next ? route('today', ['fecha' => $next]) : route('today') }}" class="p-2 text-gray-500 hover:text-gray-900" aria-label="Día siguiente">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            @endif
        </div>
    </x-slot>

    <div x-data="dayBoard(@js($payload))" class="space-y-6 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">
        @unless ($isToday)
            <a href="{{ route('today') }}" class="block lg:col-span-3 text-center text-sm rounded-xl bg-amber-50 text-amber-800 ring-1 ring-amber-200 px-3 py-2">
                Estás editando un día pasado · <span class="font-semibold underline">volver a hoy</span>
            </a>
        @endunless

        {{-- Prioridades (en escritorio: columna derecha fija) --}}
        <section class="lg:order-2 lg:sticky lg:top-20">
            <h2 class="section-title">Prioridades</h2>
            <template x-if="priorities.length">
                <ul class="card divide-y divide-gray-100">
                    <template x-for="p in priorities" :key="p.id">
                        <li class="flex items-center gap-3 px-4 py-3">
                            <span class="text-sm font-bold text-gray-300 w-4" x-text="p.position"></span>
                            <p class="flex-1 min-w-0 break-words"
                               :class="{ 'line-through text-gray-400': p.completed === true, 'text-gray-500': p.completed === false }"
                               x-text="p.text"></p>
                            <div class="flex gap-1.5 shrink-0">
                                <button type="button" @click="mark(p, true)" aria-label="Cumplida"
                                        class="w-10 h-10 rounded-full grid place-items-center ring-1 transition"
                                        :class="p.completed === true ? 'bg-emerald-500 ring-emerald-500 text-white' : 'ring-gray-300 text-gray-400'">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                </button>
                                <button type="button" @click="mark(p, false)" aria-label="No cumplida"
                                        class="w-10 h-10 rounded-full grid place-items-center ring-1 transition"
                                        :class="p.completed === false ? 'bg-rose-500 ring-rose-500 text-white' : 'ring-gray-300 text-gray-400'">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </li>
                    </template>
                </ul>
            </template>
            <template x-if="! priorities.length">
                <div class="card px-4 py-4 text-sm text-gray-500 flex items-center justify-between gap-3">
                    <span>Sin prioridades para este día.</span>
                    @if ($isToday)
                        <a href="{{ route('priorities.edit', ['para' => 'hoy']) }}" class="font-semibold text-gray-900 shrink-0">Planear hoy →</a>
                    @endif
                </div>
            </template>
        </section>

        {{-- Hábitos --}}
        <section class="lg:order-1 lg:col-span-2">
            <div class="flex items-baseline justify-between">
                <h2 class="section-title">Hábitos</h2>
                <span class="text-xs font-semibold text-gray-500 px-1" x-show="habits.length" x-text="`${done}/${habits.length}`"></span>
            </div>

            @if ($board['total'] === 0)
                <div class="card p-6 text-center space-y-3">
                    <p class="text-gray-600">{{ $hasHabits ? 'No hay hábitos activos para este día.' : 'Todavía no tienes hábitos.' }}</p>
                    <a href="{{ route('habits.create') }}" class="btn-primary">Crear hábito</a>
                </div>
            @else
                <ul class="space-y-2">
                    <template x-for="h in habits" :key="h.id">
                        <li class="card overflow-hidden">
                            <div class="flex items-stretch">
                                <button type="button" @click="toggle(h)" class="flex-1 min-w-0 flex items-center gap-3 px-4 py-3.5 text-left"
                                        :aria-pressed="h.done">
                                    <span class="w-8 h-8 shrink-0 rounded-full grid place-items-center ring-2 transition"
                                          :style="h.done ? `background:${h.color};--tw-ring-color:${h.color}` : `--tw-ring-color:${h.color}`">
                                        <svg x-show="h.done" x-cloak class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block font-medium truncate" :class="h.done && 'text-gray-500'" x-text="h.name"></span>
                                        <span class="block text-xs text-gray-500">
                                            <template x-if="h.weekly"><span x-text="`${h.week_count}/${h.target} esta semana`"></span></template>
                                            <template x-if="h.weekly && h.streak > 0"><span> · </span></template>
                                            <template x-if="h.streak > 0"><span x-text="`racha ${h.streak} ${h.streak_unit}`"></span></template>
                                            <template x-if="!h.weekly && h.streak === 0"><span>Diario</span></template>
                                        </span>
                                    </span>
                                </button>
                                <button type="button" @click="h.noteOpen = !h.noteOpen"
                                        class="px-4 grid place-items-center" :class="h.note ? 'text-gray-900' : 'text-gray-300'" aria-label="Nota">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                                </button>
                            </div>
                            <p x-show="h.note && !h.noteOpen" x-cloak x-text="h.note" class="px-4 pb-3 -mt-1 text-sm text-gray-600 whitespace-pre-line"></p>
                            <div x-show="h.noteOpen" x-cloak class="px-4 pb-4 space-y-2">
                                <textarea x-effect="h.noteOpen && $nextTick(() => $el.focus())" x-model="h.draft" rows="2" maxlength="500" class="field text-sm" placeholder="Nota opcional…"></textarea>
                                <div class="flex gap-2 justify-end">
                                    <button type="button" class="btn-ghost py-2 text-sm" @click="h.draft = h.note ?? ''; h.noteOpen = false">Cancelar</button>
                                    <button type="button" class="btn-primary py-2 text-sm" @click="saveNote(h)">Guardar nota</button>
                                </div>
                            </div>
                        </li>
                    </template>
                </ul>
            @endif
        </section>

        <div x-show="error" x-cloak x-transition
             class="fixed left-4 right-4 lg:left-64 bottom-[calc(5rem+env(safe-area-inset-bottom))] lg:bottom-6 z-40 max-w-lg mx-auto rounded-xl bg-rose-600 text-white text-sm px-4 py-3 shadow-lg"
             x-text="error"></div>
    </div>
</x-app-layout>
