<x-app-layout title="Informe">
    <x-slot name="header">
        <div class="flex items-center gap-1">
            <a href="{{ route('progress') }}" class="p-2 -ml-2 text-gray-500" aria-label="Volver a Progreso">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            </a>
            <h1 class="text-xl font-bold">Informe para IA</h1>
        </div>
    </x-slot>

    <div x-data="{
            copied: false,
            async copy() {
                const text = this.$refs.md.value;
                try {
                    await navigator.clipboard.writeText(text);
                } catch {
                    // Sin contexto seguro (http en la red local) no hay Clipboard API.
                    this.$refs.md.select();
                    document.execCommand('copy');
                }
                this.copied = true;
                setTimeout(() => this.copied = false, 2500);
            }
         }" class="space-y-4">

        <p class="text-sm text-gray-600 px-1">Rango del informe:</p>
        <nav class="grid grid-cols-3 gap-2 -mt-2" aria-label="Rango">
            @foreach ($ranges as $r)
                <a href="{{ route('report', ['dias' => $r]) }}" @class(['btn text-sm py-2', 'bg-gray-900 text-white' => $r === $days, 'btn-ghost' => $r !== $days])
                   @if ($r === $days) aria-current="page" @endif>{{ $r }} días</a>
            @endforeach
        </nav>

        <button type="button" @click="copy()" class="btn-primary w-full">
            <span x-show="!copied">Copiar al portapapeles</span>
            <span x-show="copied" x-cloak>✓ Copiado. Pégalo en tu chat con la IA</span>
        </button>

        <textarea x-ref="md" readonly rows="18" class="field font-mono text-xs leading-relaxed bg-white" aria-label="Informe en Markdown">{{ $markdown }}</textarea>

        <a href="{{ route('report.download', ['dias' => $days]) }}" class="btn-ghost w-full text-sm">Descargar .md</a>
    </div>
</x-app-layout>
