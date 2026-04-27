<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Exports\SubmissionCsvExporter;

Route::redirect('/', '/admin');

Route::middleware('auth')->get('/exports/submissions.csv', function (SubmissionCsvExporter $exporter) {
    abort_unless(auth()->user()->isAdmin(), 403);

    return $exporter->download();
})->name('exports.submissions');
