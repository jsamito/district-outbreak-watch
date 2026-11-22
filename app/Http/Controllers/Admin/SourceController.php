<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataSource;
use App\Models\ImportBatch;
use App\Models\IngestEvent;
use Illuminate\View\View;

class SourceController extends Controller
{
    public function index(): View
    {
        return view('admin.sources', [
            'title' => 'Data sources', 'sources' => DataSource::withCount('batches')->orderBy('name')->get(),
            'batches' => ImportBatch::with('source')->latest('id')->limit(25)->get(),
            'ingest' => IngestEvent::selectRaw('channel, status, COUNT(*) AS n')->groupBy('channel', 'status')->get(),
        ]);
    }
}
