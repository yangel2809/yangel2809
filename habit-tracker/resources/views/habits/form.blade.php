@php $editing = $habit->exists; @endphp
<x-app-layout :title="$editing ? 'Editar hábito' : 'Nuevo hábito'">
    <x-slot name="header">
        <div class="flex items-center gap-1">
            <a href="{{ route('habits.index') }}" class="p-2 -ml-2 text-gray-500" aria-label="Volver">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            </a>
            <h1 class="text-xl font-bold">{{ $editing ? 'Editar hábito' : 'Nuevo hábito' }}</h1>
        </div>
    </x-slot>

    <form method="POST" action="{{ $editing ? route('habits.update', $habit) : route('habits.store') }}"
          x-data="{ freq: @js(old('frequency_type', $habit->frequency_type)) }" class="space-y-5">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div>
            <label for="name" class="label">Nombre</label>
            <input id="name" name="name" type="text" value="{{ old('name', $habit->name) }}" maxlength="100" required
                   @unless ($editing) autofocus @endunless class="field" placeholder="Ej. Leer 20 minutos">
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

        <fieldset>
            <legend class="label">Frecuencia</legend>
            <div class="grid grid-cols-2 gap-2">
                @foreach (['daily' => 'Diario', 'weekly' => 'Veces por semana'] as $value => $label)
                    <label class="btn-ghost cursor-pointer text-sm" :class="freq === '{{ $value }}' && '!bg-gray-900 !text-white !ring-gray-900'">
                        <input type="radio" name="frequency_type" value="{{ $value }}" x-model="freq" class="sr-only">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <div x-show="freq === 'weekly'" x-cloak class="mt-3 flex items-center gap-3">
                <select name="weekly_target" class="field w-24" :disabled="freq !== 'weekly'">
                    @foreach (range(1, 6) as $n)
                        <option value="{{ $n }}" @selected((int) old('weekly_target', $habit->weekly_target ?? 3) === $n)>{{ $n }}</option>
                    @endforeach
                </select>
                <span class="text-sm text-gray-600">veces por semana (lunes a domingo)</span>
            </div>
            <x-input-error :messages="$errors->get('frequency_type')" class="mt-1" />
            <x-input-error :messages="$errors->get('weekly_target')" class="mt-1" />
        </fieldset>

        <fieldset>
            <legend class="label">Color</legend>
            <div class="flex flex-wrap gap-3">
                @foreach ($colors as $color)
                    <label class="cursor-pointer">
                        <input type="radio" name="color" value="{{ $color }}" class="peer sr-only" @checked(old('color', $habit->color) === $color)>
                        <span class="block w-10 h-10 rounded-full ring-offset-2 peer-checked:ring-2 peer-checked:ring-gray-900 peer-focus-visible:ring-2" style="background: {{ $color }}"></span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('color')" class="mt-1" />
        </fieldset>

        <div>
            <label for="start_date" class="label">Cuenta desde</label>
            <input id="start_date" name="start_date" type="date" value="{{ old('start_date', $habit->start_date) }}" required class="field">
            <p class="text-xs text-gray-500 mt-1">Los días anteriores no cuentan para rachas ni porcentajes.</p>
            <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
        </div>

        <button class="btn-primary w-full">{{ $editing ? 'Guardar cambios' : 'Crear hábito' }}</button>
    </form>

    @if ($editing)
        <div class="mt-8 space-y-3">
            <form method="POST" action="{{ route('habits.archive', $habit) }}">
                @csrf @method('PATCH')
                <button class="btn-ghost w-full">Archivar</button>
                <p class="text-xs text-gray-500 mt-1 text-center">Deja de aparecer en Hoy; el historial se conserva.</p>
            </form>
            <form method="POST" action="{{ route('habits.destroy', $habit) }}"
                  onsubmit="return confirm('¿Eliminar «{{ addslashes($habit->name) }}» y TODO su historial? No se puede deshacer.')">
                @csrf @method('DELETE')
                <button class="btn-danger w-full">Eliminar definitivamente</button>
            </form>
        </div>
    @endif
</x-app-layout>
