<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Services\DayBoard;
use App\Support\LocalDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HabitLogController extends Controller
{
    /**
     * Fija el estado (y opcionalmente la nota) del hábito en una fecha.
     * El cliente envía el estado deseado, no un "toggle": así un doble toque
     * o un reintento de red no invierte el resultado.
     */
    public function update(Request $request, Habit $habit, DayBoard $board)
    {
        Gate::authorize('manage', $habit);

        $data = $request->validate([
            'date' => ['required', 'string'],
            'completed' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $date = $data['date'];
        if (! LocalDate::isEditable($date) || $date < $habit->startDate()) {
            throw ValidationException::withMessages(['date' => 'Solo puedes registrar los últimos '.LocalDate::EDITABLE_DAYS.' días.']);
        }
        if ($habit->isArchived()) {
            throw ValidationException::withMessages(['habit' => 'El hábito está archivado.']);
        }

        $values = ['habit_id' => $habit->id, 'date' => $date];
        $update = [];
        if (array_key_exists('completed', $data)) {
            $values['completed'] = (int) filter_var($data['completed'], FILTER_VALIDATE_BOOLEAN);
            $update[] = 'completed';
        }
        if (array_key_exists('note', $data)) {
            $values['note'] = filled($data['note']) ? trim($data['note']) : null;
            $update[] = 'note';
        }
        $values += ['completed' => 0];

        // INSERT ... ON DUPLICATE KEY UPDATE: atómico frente a dos toques seguidos.
        if ($update !== []) {
            HabitLog::query()->upsert([$values], ['habit_id', 'date'], $update);
        }

        $log = HabitLog::query()->where('habit_id', $habit->id)->where('date', $date)->first();
        $habit->load(['logs' => fn ($q) => $q->where('completed', true)]);
        $item = $board->habitItem($habit, $log, LocalDate::parse($date));

        if ($request->wantsJson()) {
            return response()->json($item);
        }

        return back();
    }
}
