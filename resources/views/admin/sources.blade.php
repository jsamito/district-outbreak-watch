@extends('layouts.app', ['title' => 'Data sources'])
@section('content')
    <div class="box"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-database" aria-hidden="true"></i> Datasets, licences and provenance</h3></div>
        <div class="box-body no-padding">
            <table class="table table-hover"><caption class="sr-only">Data sources</caption>
                <thead><tr><th scope="col">Dataset</th><th scope="col">Licence</th><th scope="col">Accessed</th><th scope="col">Type</th><th scope="col" class="text-right">Loads</th></tr></thead><tbody>
                @foreach ($sources as $s)<tr><td><strong>{{ $s->name }}</strong><small class="text-muted" style="display:block">{{ $s->citation }}</small></td><td>{{ $s->licence }}</td><td>{{ $s->accessed_at?->format('d M Y') }}</td><td>{{ $s->synthetic ? 'Generated sample' : 'Open data' }}</td><td class="text-right">{{ $s->batches_count }}</td></tr>@endforeach
            </tbody></table>
        </div></div>
    <div class="row">
        <div class="col-md-8"><div class="box"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-download" aria-hidden="true"></i> Recent data loads</h3></div>
            <div class="box-body no-padding"><table class="table table-condensed"><thead><tr><th scope="col">Finished</th><th scope="col">Process</th><th scope="col" class="text-right">Rows</th><th scope="col" class="text-right">Rejected</th><th scope="col">Status</th></tr></thead><tbody>
                @foreach ($batches as $b)<tr><td>{{ $b->finished_at?->format('d M Y H:i') ?? '-' }}</td><td>{{ $b->command }}</td><td class="text-right">{{ number_format($b->rows_in) }}</td><td class="text-right">{{ number_format($b->rows_rejected) }}</td><td>{{ $b->status }}</td></tr>@endforeach
            </tbody></table></div></div></div>
        <div class="col-md-4"><div class="box"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-plug" aria-hidden="true"></i> Notifications received</h3></div>
            <div class="box-body no-padding"><table class="table table-condensed"><thead><tr><th scope="col">Channel</th><th scope="col">Status</th><th scope="col" class="text-right">Count</th></tr></thead><tbody>
                @forelse ($ingest as $i)<tr><td>{{ $i->channel }}</td><td>{{ $i->status }}</td><td class="text-right">{{ number_format($i->n) }}</td></tr>@empty<tr><td colspan="3" class="text-muted">None yet.</td></tr>@endforelse
            </tbody></table></div></div></div>
    </div>
@endsection
