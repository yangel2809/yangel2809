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

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn-ghost w-full">Cerrar sesión</button>
            </form>
        </div>
    </div>
</x-app-layout>
