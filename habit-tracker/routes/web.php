<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\HabitLogController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TodayController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [TodayController::class, 'index'])->name('today');

    Route::put('/registro/{habit}', [HabitLogController::class, 'update'])->name('logs.update');

    Route::get('/manana', [PriorityController::class, 'edit'])->name('priorities.edit');
    Route::put('/manana', [PriorityController::class, 'update'])->name('priorities.update');
    Route::patch('/prioridades/{priority}', [PriorityController::class, 'mark'])->name('priorities.mark');

    Route::get('/progreso', [ProgressController::class, 'index'])->name('progress');
    Route::get('/informe', [ReportController::class, 'show'])->name('report');
    Route::get('/informe/descargar', [ReportController::class, 'download'])->name('report.download');
    Route::get('/exportar', ExportController::class)->name('export');

    Route::resource('habitos', HabitController::class)
        ->except('show')
        ->parameters(['habitos' => 'habit'])
        ->names('habits');
    Route::patch('/habitos/{habit}/archivar', [HabitController::class, 'archive'])->name('habits.archive');
    Route::patch('/habitos/{habit}/restaurar', [HabitController::class, 'unarchive'])->name('habits.unarchive');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
