@php use App\Support\LocalDate; @endphp
<x-app-layout :title="$forToday ? 'Prioridades de hoy' : 'Mañana'">
    <x-slot name="header">
        <div class="leading-tight">
            <h1 class="text-xl font-bold">{{ $forToday ? 'Prioridades de hoy' : 'Mañana' }}</h1>
            <p class="text-xs text-gray-500">{{ LocalDate::human($day) }}</p>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('priorities.update') }}" class="space-y-4">
        @csrf
        @method('PUT')
        <input type="hidden" name="date" value="{{ $day->format('Y-m-d') }}">

        <p class="text-sm text-gray-600 px-1">
            Máximo {{ count($texts) }}. Lo que no entre aquí no es prioridad.
            @unless ($forToday) Mañana aparecerán en <strong>Hoy</strong> para marcarlas. @endunless
        </p>

        <div class="card divide-y divide-gray-100">
            @foreach ($texts as $i => $text)
                <label class="flex items-center gap-3 px-4">
                    <span class="text-lg font-bold text-gray-300 w-4">{{ $i + 1 }}</span>
                    <input type="text" name="texts[]" value="{{ old("texts.$i", $text) }}" maxlength="200"
                           @if ($i === 0 && $text === '') autofocus @endif
                           enterkeyhint="{{ $i === count($texts) - 1 ? 'done' : 'next' }}"
                           class="flex-1 border-0 px-0 py-4 text-base focus:ring-0 placeholder:text-gray-300"
                           placeholder="{{ ['Lo más importante', 'Segunda prioridad', 'Tercera prioridad'][$i] ?? 'Prioridad' }}">
                </label>
                <x-input-error :messages="$errors->get('texts.'.$i)" class="px-4 pb-2" />
            @endforeach
        </div>
        <x-input-error :messages="$errors->get('date')" />
        <x-input-error :messages="$errors->get('texts')" />

        <button class="btn-primary w-full">Guardar</button>

        <p class="text-center text-sm">
            @if ($forToday)
                <a href="{{ route('priorities.edit') }}" class="text-gray-500 underline">Planear mañana</a>
            @else
                <a href="{{ route('priorities.edit', ['para' => 'hoy']) }}" class="text-gray-500 underline">Editar las de hoy</a>
            @endif
        </p>
    </form>
</x-app-layout>
