<x-app-layout title="Hábitos">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold">Hábitos</h1>
            <a href="{{ route('habits.create') }}" class="btn-primary py-2 px-3 text-sm">+ Nuevo</a>
        </div>
    </x-slot>

    @if ($active->isEmpty())
        <div class="card p-6 text-center text-gray-600">
            Crea el primero. Empieza con uno o dos; es mejor cumplir pocos que abandonar muchos.
        </div>
    @else
        <ul class="card divide-y divide-gray-100">
            @foreach ($active as $habit)
                <li>
                    <a href="{{ route('habits.edit', $habit) }}" class="flex items-center gap-3 px-4 py-3.5">
                        <span class="w-3 h-3 rounded-full shrink-0" style="background: {{ $habit->color }}"></span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-medium truncate">{{ $habit->name }}</span>
                            <span class="block text-xs text-gray-500">{{ $habit->frequencyLabel() }}</span>
                        </span>
                        <svg class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($archived->isNotEmpty())
        <details class="mt-6">
            <summary class="section-title cursor-pointer">Archivados ({{ $archived->count() }})</summary>
            <ul class="card divide-y divide-gray-100">
                @foreach ($archived as $habit)
                    <li class="flex items-center gap-3 px-4 py-3">
                        <span class="w-3 h-3 rounded-full shrink-0 opacity-50" style="background: {{ $habit->color }}"></span>
                        <span class="flex-1 min-w-0 truncate text-gray-500">{{ $habit->name }}</span>
                        <form method="POST" action="{{ route('habits.unarchive', $habit) }}">
                            @csrf @method('PATCH')
                            <button class="text-sm font-semibold text-gray-900 px-2 py-1">Restaurar</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</x-app-layout>
