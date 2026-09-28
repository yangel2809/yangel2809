<?php

namespace App\Http\Controllers;

use App\Http\Requests\HabitRequest;
use App\Models\Habit;
use App\Support\LocalDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HabitController extends Controller
{
    public function index(Request $request)
    {
        $habits = $request->user()->habits()->ordered()->get();

        return view('habits.index', [
            'active' => $habits->whereNull('archived_at'),
            'archived' => $habits->whereNotNull('archived_at'),
        ]);
    }

    public function create(Request $request)
    {
        $n = $request->user()->habits()->count();

        return view('habits.form', [
            'habit' => new Habit([
                'frequency_type' => Habit::DAILY,
                'weekly_target' => 3,
                'color' => HabitRequest::COLORS[$n % count(HabitRequest::COLORS)],
                'start_date' => LocalDate::today()->format('Y-m-d'),
            ]),
            'colors' => HabitRequest::COLORS,
        ]);
    }

    public function store(HabitRequest $request)
    {
        $user = $request->user();
        $user->habits()->create($request->habitData() + [
            'sort_order' => (int) $user->habits()->max('sort_order') + 1,
        ]);

        return redirect()->route('habits.index')->with('status', 'Hábito creado.');
    }

    public function edit(Habit $habit)
    {
        Gate::authorize('manage', $habit);

        return view('habits.form', ['habit' => $habit, 'colors' => HabitRequest::COLORS]);
    }

    public function update(HabitRequest $request, Habit $habit)
    {
        $habit->update($request->habitData());

        return redirect()->route('habits.index')->with('status', 'Hábito actualizado.');
    }

    public function archive(Habit $habit)
    {
        Gate::authorize('manage', $habit);
        $habit->update(['archived_at' => now()]);

        return back()->with('status', "«{$habit->name}» archivado. Su historial se conserva.");
    }

    public function unarchive(Habit $habit)
    {
        Gate::authorize('manage', $habit);
        $habit->update(['archived_at' => null]);

        return back()->with('status', "«{$habit->name}» restaurado.");
    }

    public function destroy(Habit $habit)
    {
        Gate::authorize('manage', $habit);
        $habit->delete();

        return redirect()->route('habits.index')->with('status', "«{$habit->name}» eliminado junto con su historial.");
    }
}
