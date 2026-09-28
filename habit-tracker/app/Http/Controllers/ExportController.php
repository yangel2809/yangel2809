<?php

namespace App\Http\Controllers;

use App\Services\DataExporter;
use App\Support\LocalDate;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __invoke(Request $request, DataExporter $exporter)
    {
        $name = 'habitos-respaldo-'.LocalDate::today()->format('Y-m-d').'.json';

        return response()->json(
            $exporter->export($request->user()),
            200,
            ['Content-Disposition' => "attachment; filename=\"{$name}\""],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
