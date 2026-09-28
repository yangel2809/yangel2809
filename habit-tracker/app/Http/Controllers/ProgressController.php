<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Services\FeedbackService;
use App\Services\HabitStatsService;
use App\Support\LocalDate;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function index(Request $request, HabitStatsService $stats, FeedbackService $feedback)
    {
        $user = $request->user();
        $today = LocalDate::today();
        $from30 = $today->subDays(29);

        $habits = $user->habits()->active()->ordered()
            ->where('start_date', '<=', $today->format('Y-m-d'))
            ->with(['logs' => fn ($q) => $q->where('completed', true)->select('id', 'habit_id', 'date', 'completed')])
            ->get();

        $summary = collect($stats->summary($habits, $today))->map(fn ($r) => [
            'id' => $r['habit']->id,
            'name' => $r['habit']->name,
            'color' => $r['habit']->color,
            'weekly' => $r['habit']->isWeekly(),
            'frequency' => $r['habit']->frequencyLabel(),
            'rate7' => $r['rate7']['rate'],
            'rate30' => $r['rate30']['rate'],
            'current' => $r['current'],
            'best' => $r['best'],
            'unit' => $r['unit'],
        ])->all();

        $heatmaps = ['all' => $stats->heatmap($habits, $from30, $today, $today)];
        foreach ($habits as $habit) {
            $heatmaps[$habit->id] = $stats->heatmap([$habit], $from30, $today, $today);
        }

        return view('progress', [
            'today' => $today,
            'from30' => $from30,
            'summary' => $summary,
            'heatmaps' => $heatmaps,
            'heatmapOffset' => $from30->isoWeekday() - 1,
            'weekday' => $stats->weekdayBreakdown($habits, $from30, $today, $today),
            'overall7' => $stats->overallRate($habits, $today->subDays(6), $today, $today),
            'overall30' => $stats->overallRate($habits, $from30, $today, $today),
            'priorities7' => $stats->prioritySummary($user, $today->subDays(6), $today),
            'feedback' => $feedback->messages($user, $habits, $today),
            'hasDaily' => $habits->contains(fn (Habit $h) => ! $h->isWeekly()),
        ]);
    }
}
