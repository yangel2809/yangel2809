@php
    use App\Services\FeedbackService;
    use App\Support\LocalDate;

    $short = [1 => 'L', 'M', 'X', 'J', 'V', 'S', 'D'];
    $weekdayData = collect($weekday)->map(fn ($d, $i) => $d + ['short' => $short[$i], 'name' => ucfirst(FeedbackService::WEEKDAYS[$i])])->values();
    $pct = fn ($v) => $v === null ? '—' : round($v).'%';
    $tones = [
        'good' => ['bg-emerald-50 ring-emerald-200', 'text-emerald-700', 'M4.5 12.75l6 6 9-13.5'],
        'warn' => ['bg-amber-50 ring-amber-200', 'text-amber-700', 'M12 9v3.75m0 3.75h.008v.008H12v-.008Z'],
        'info' => ['bg-white ring-gray-200', 'text-gray-500', 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M12 8.25h.008v.008H12V8.25Z'],
    ];
@endphp

<x-app-layout title="Progreso">
    <x-slot name="head">@vite('resources/js/progress.js')</x-slot>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-2">
        <div class="leading-tight">
            <h1 class="text-xl font-bold">Progreso</h1>
            <p class="text-xs text-gray-500">{{ LocalDate::parse($from30)->locale('es')->isoFormat('D MMM') }} – {{ LocalDate::parse($today)->locale('es')->isoFormat('D MMM') }}</p>
        </div>
        <a href="{{ route('report') }}" class="btn-primary py-2 px-3 text-sm shrink-0">Generar informe</a>
        </div>
    </x-slot>

    <script type="application/json" id="progress-data">@json(['habits' => $summary, 'weekday' => $weekdayData])</script>

    @if (empty($summary))
        <div class="card p-6 text-center space-y-3">
            <p class="text-gray-600">Crea un hábito y regístralo unos días para ver tu progreso.</p>
            <a href="{{ route('habits.create') }}" class="btn-primary">Crear hábito</a>
        </div>
    @else
        <div class="space-y-6">
            {{-- Retroalimentación --}}
            <section>
                <h2 class="section-title">Lo que dicen tus datos</h2>
                <ul class="space-y-2">
                    @foreach ($feedback as $msg)
                        @php [$box, $ink, $icon] = $tones[$msg['tone']]; @endphp
                        <li class="flex gap-3 rounded-2xl ring-1 px-4 py-3 {{ $box }}">
                            <svg class="w-5 h-5 shrink-0 mt-0.5 {{ $ink }}" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                            <p class="text-sm text-gray-800">{{ $msg['text'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- KPIs --}}
            <section class="grid grid-cols-3 gap-2">
                @foreach ([['Últimos 7 días', $pct($overall7['rate'])], ['Últimos 30 días', $pct($overall30['rate'])], ['Prioridades 7 d', $priorities7['completed'] + $priorities7['not_completed'] ? $priorities7['completed'].'/'.($priorities7['completed'] + $priorities7['not_completed']) : '—']] as [$label, $value])
                    <div class="card px-3 py-3">
                        <p class="text-[11px] leading-tight text-gray-500">{{ $label }}</p>
                        <p class="text-2xl font-semibold mt-1">{{ $value }}</p>
                    </div>
                @endforeach
            </section>

            {{-- Cumplimiento por hábito --}}
            <section class="card p-4">
                <h2 class="font-semibold">Cumplimiento por hábito</h2>
                <div class="flex gap-4 text-xs text-gray-600 mt-1 mb-3">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background:#256abf"></span>Últimos 7 días</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background:#86b6ef"></span>Últimos 30 días</span>
                </div>
                <div style="height: {{ count($summary) * 48 + 28 }}px"><canvas id="chart-habits" role="img" aria-label="Porcentaje de cumplimiento por hábito, últimos 7 y 30 días"></canvas></div>
                <p class="text-xs text-gray-500 mt-2">Semanales: % de semanas en que llegaste a la meta.</p>
            </section>

            {{-- Rachas (y vista en tabla de los porcentajes) --}}
            <section class="card">
                <h2 class="font-semibold px-4 pt-4 pb-2">Rachas</h2>
                <table class="w-full text-sm table-fixed">
                    <thead class="text-xs text-gray-500">
                        <tr class="border-b border-gray-100">
                            <th class="text-left font-medium px-4 py-2 w-auto">Hábito</th>
                            <th class="text-right font-medium px-2 py-2 w-20">Actual</th>
                            <th class="text-right font-medium px-4 py-2 w-20">Mejor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 tabular-nums">
                        @foreach ($summary as $row)
                            <tr>
                                <td class="px-4 py-2.5">
                                    <span class="flex items-center gap-2 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $row['color'] }}"></span>
                                        <span class="min-w-0">
                                            <span class="block truncate">{{ $row['name'] }}</span>
                                            <span class="block text-xs text-gray-500">7 d {{ $pct($row['rate7']) }} · 30 d {{ $pct($row['rate30']) }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td class="text-right px-2 font-semibold whitespace-nowrap">{{ $row['current'] }} <span class="font-normal text-gray-500">{{ $row['weekly'] ? 'sem' : 'd' }}</span></td>
                                <td class="text-right px-4 whitespace-nowrap">{{ $row['best'] }} <span class="text-gray-500">{{ $row['weekly'] ? 'sem' : 'd' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            {{-- Heatmap 30 días --}}
            <section class="card p-4"
                     x-data="{ sel: 'all', maps: @js($heatmaps), picked: null,
                               color(c) {
                                   if (c.future) return 'transparent';
                                   if (c.total === 0) return c.weekly_done ? '#6da7ec' : 'transparent';
                                   const r = c.rate;
                                   return r === 0 ? '#dbe9fb' : r < 34 ? '#9ec5f4' : r < 67 ? '#6da7ec' : r < 100 ? '#3987e5' : '#1c5cab';
                               },
                               label(c) {
                                   const d = new Date(c.date + 'T12:00:00').toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' });
                                   if (c.future) return d;
                                   if (c.total === 0) return c.weekly_done ? `${d}: hecho` : `${d}: sin hábitos diarios`;
                                   return `${d}: ${c.done}/${c.total} (${Math.round(c.rate)}%)` + (c.weekly_done ? ` + ${c.weekly_done} semanal` : '');
                               } }">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="font-semibold">Últimos 30 días</h2>
                    <select x-model="sel" @change="picked = null" class="field w-auto max-w-[55%] py-1.5 text-sm">
                        <option value="all">Todos</option>
                        @foreach ($summary as $row)
                            <option value="{{ $row['id'] }}">{{ $row['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-7 gap-1.5 text-center">
                    @foreach ($short as $d)
                        <span class="text-[11px] text-gray-400">{{ $d }}</span>
                    @endforeach
                    @for ($i = 0; $i < $heatmapOffset; $i++)
                        <span></span>
                    @endfor
                    <template x-for="c in maps[sel]" :key="sel + c.date">
                        <button type="button" @click="picked = c" :title="label(c)" :aria-label="label(c)"
                                class="aspect-square rounded-md transition"
                                :class="picked && picked.date === c.date ? 'ring-2 ring-gray-900 ring-offset-1' : (c.total === 0 && !c.weekly_done ? 'ring-1 ring-inset ring-gray-200' : '')"
                                :style="`background:${color(c)}`"></button>
                    </template>
                </div>
                <p class="text-sm text-gray-700 mt-3 min-h-[1.25rem]" x-text="picked ? label(picked) : 'Toca un día para ver el detalle.'"></p>
                <div class="flex items-center gap-1.5 mt-2 text-[11px] text-gray-500">
                    <span>0%</span>
                    @foreach (['#dbe9fb', '#9ec5f4', '#6da7ec', '#3987e5', '#1c5cab'] as $c)
                        <span class="w-3.5 h-3.5 rounded-sm" style="background: {{ $c }}"></span>
                    @endforeach
                    <span>100%</span>
                    <span class="ml-auto flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded-sm ring-1 ring-inset ring-gray-200"></span>sin datos</span>
                </div>
            </section>

            {{-- Día de la semana --}}
            <section class="card p-4">
                <h2 class="font-semibold">Por día de la semana</h2>
                <p class="text-xs text-gray-500 mb-2">Últimos 30 días · solo hábitos diarios (en los semanales un día sin hacer no es fallo).</p>
                @if ($hasDaily)
                    <div class="h-48"><canvas id="chart-weekday" role="img" aria-label="Cumplimiento por día de la semana"></canvas></div>
                    <p class="sr-only">
                        @foreach ($weekdayData as $d) {{ $d['name'] }}: {{ $pct($d['rate']) }}. @endforeach
                    </p>
                @else
                    <p class="text-sm text-gray-500 py-4">Aparece cuando tengas algún hábito diario.</p>
                @endif
            </section>
        </div>
    @endif
</x-app-layout>
