<?php

namespace App\Http\Controllers;

use App\Services\ReportGenerator;
use App\Support\LocalDate;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function show(Request $request, ReportGenerator $reports)
    {
        $days = $this->days($request);

        return view('report', [
            'days' => $days,
            'ranges' => ReportGenerator::RANGES,
            'markdown' => $reports->generate($request->user(), $days),
        ]);
    }

    public function download(Request $request, ReportGenerator $reports)
    {
        $days = $this->days($request);
        $name = 'informe-habitos-'.LocalDate::today()->format('Y-m-d')."-{$days}d.md";

        return response($reports->generate($request->user(), $days), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    private function days(Request $request): int
    {
        $days = (int) $request->query('dias', 7);

        return in_array($days, ReportGenerator::RANGES, true) ? $days : 7;
    }
}
