<?php

namespace App\Http\Controllers;

use App\Models\DailyPriority;
use App\Services\DayBoard;
use App\Support\LocalDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PriorityController extends Controller
{
    public function edit(Request $request)
    {
        $today = LocalDate::today();
        $forToday = $request->query('para') === 'hoy';
        $day = $forToday ? $today : $today->addDay();

        $existing = $request->user()->priorities()
            ->where('date', $day->format('Y-m-d'))
            ->orderBy('position')
            ->pluck('text')
            ->all();

        return view('tomorrow', [
            'day' => $day,
            'forToday' => $forToday,
            'texts' => array_pad($existing, config('habits.max_priorities'), ''),
        ]);
    }

    /**
     * Reemplaza las prioridades del día (hoy o mañana). Si un texto no cambia
     * se conserva si ya estaba marcada como cumplida o no.
     */
    public function update(Request $request)
    {
        $today = LocalDate::today();
        $allowed = [$today->format('Y-m-d'), $today->addDay()->format('Y-m-d')];
        $max = config('habits.max_priorities');

        $data = $request->validate([
            'date' => ['required', Rule::in($allowed)],
            'texts' => ['present', 'array', "max:{$max}"],
            'texts.*' => ['nullable', 'string', 'max:200'],
        ], [
            'date.in' => 'Solo puedes planear hoy o mañana.',
        ]);

        $texts = array_values(array_filter(array_map(fn ($t) => trim((string) $t), $data['texts']), 'strlen'));
        $user = $request->user();

        DB::transaction(function () use ($user, $data, $texts) {
            $previous = $user->priorities()->where('date', $data['date'])->lockForUpdate()->get()->keyBy('text');
            $user->priorities()->where('date', $data['date'])->delete();

            foreach ($texts as $i => $text) {
                $user->priorities()->create([
                    'date' => $data['date'],
                    'position' => $i + 1,
                    'text' => $text,
                    'completed' => $previous->get($text)?->completed,
                ]);
            }
        });

        $label = $data['date'] === $allowed[0] ? 'hoy' : 'mañana';
        $msg = $texts === [] ? "Sin prioridades para {$label}." : count($texts)." ".(count($texts) === 1 ? 'prioridad guardada' : 'prioridades guardadas')." para {$label}.";

        return redirect()->route('priorities.edit', $label === 'hoy' ? ['para' => 'hoy'] : [])->with('status', $msg);
    }

    /**
     * Marca una prioridad: completed = 1 (cumplida), 0 (no cumplida) o vacío (sin marcar).
     */
    public function mark(Request $request, DailyPriority $priority, DayBoard $board)
    {
        Gate::authorize('manage', $priority);

        $data = $request->validate(['completed' => ['present', 'nullable', 'boolean']]);

        if (! LocalDate::isEditable($priority->date)) {
            throw ValidationException::withMessages(['date' => 'Esa prioridad ya no se puede modificar.']);
        }

        $priority->update(['completed' => $data['completed'] === null ? null : (bool) $data['completed']]);

        return $request->wantsJson() ? response()->json($board->priorityItem($priority)) : back();
    }
}
