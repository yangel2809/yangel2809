<?php

namespace App\Http\Controllers;

use App\Services\DayBoard;
use App\Support\LocalDate;
use Illuminate\Http\Request;

class TodayController extends Controller
{
    public function index(Request $request, DayBoard $board)
    {
        $today = LocalDate::today();
        $requested = $request->query('fecha');

        if ($requested !== null && ! LocalDate::isEditable($requested)) {
            return redirect()->route('today');
        }

        $day = $requested ? LocalDate::parse($requested) : $today;
        $isToday = $day->eq($today);

        return view('today', [
            'day' => $day,
            'isToday' => $isToday,
            'prev' => LocalDate::isEditable($d = $day->subDay()->format('Y-m-d')) ? $d : null,
            'next' => $isToday ? null : ($day->addDay()->eq($today) ? '' : $day->addDay()->format('Y-m-d')),
            'board' => $board->build($request->user(), $day),
            'hasHabits' => $request->user()->habits()->exists(),
        ]);
    }
}
