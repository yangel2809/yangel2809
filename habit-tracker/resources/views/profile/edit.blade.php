<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-bold">Cuenta</h1>
    </x-slot>

    <div>
        <div class="space-y-4">
            <div class="card p-4">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="card p-4">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="card p-4">
                <h2 class="text-lg font-medium text-gray-900">Respaldo</h2>
                <p class="mt-1 text-sm text-gray-600">Descarga todos tus hábitos, registros, notas y prioridades en JSON.</p>
                <a href="{{ route('export') }}" class="btn-ghost w-full mt-4">Exportar datos (JSON)</a>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn-ghost w-full">Cerrar sesión</button>
            </form>
        </div>
    </div>
</x-app-layout>
