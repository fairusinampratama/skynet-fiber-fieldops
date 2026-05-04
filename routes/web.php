<?php

use App\Filament\Exports\SubmissionCsvExporter;
use App\Services\AssetMapDataService;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->get('/exports/submissions.csv', function (SubmissionCsvExporter $exporter) {
    abort_unless(auth()->user()->isAdmin(), 403);

    return $exporter->download();
})->name('exports.submissions');

Route::middleware('auth')->get('/admin/asset-map-data', function (AssetMapDataService $service) {
    abort_unless(auth()->user()->isAdmin(), 403);

    return response()->json($service->payload([
        'project_id' => request('project_id'),
        'area_id' => request('area_id'),
        'status' => request('status'),
        'mapping_state' => request('mapping_state', 'all'),
    ]));
})->name('asset-map.data');
