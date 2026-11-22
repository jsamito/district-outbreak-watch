@extends('layouts.app', ['title' => 'Audit log'])
@section('subtitle', $full ? 'Full log' : 'Summary of the last 30 days')
@section('content')
    <div class="row">
        <div class="col-md-5">
            <div class="box"><div class="box-header with-border"><h2 class="box-title"><i class="fa fa-bar-chart" aria-hidden="true"></i> Activity, last 30 days</h2></div>
                <div class="box-body no-padding"><table class="table table-condensed"><thead><tr><th scope="col">Type</th><th scope="col">Action</th><th scope="col" class="text-right">Count</th></tr></thead><tbody>
                    @foreach ($summary as $s)<tr><td>{{ $s->category }}</td><td>{{ str_replace('_', ' ', $s->action) }}</td><td class="text-right">{{ number_format($s->n) }}</td></tr>@endforeach
                </tbody></table></div></div>
            <div class="box"><div class="box-header with-border"><h2 class="box-title"><i class="fa fa-users" aria-hidden="true"></i> Most active users</h2></div>
                <ul class="ow-list">@foreach ($topUsers as $t)<li><span class="ow-list-main">{{ $t->user?->name ?? 'Deleted user' }}</span><span class="ow-list-val">{{ number_format($t->n) }}</span></li>@endforeach</ul></div>
        </div>
        @if ($full)
        <div class="col-md-7">
            <form method="GET" class="box ow-filters" aria-label="Filter the audit log"><div class="box-body"><div class="row">
                <div class="col-sm-4 form-group"><label for="a-cat">Type</label><select id="a-cat" name="category" class="form-control"><option value="">All</option>@foreach ($categories as $c)<option @selected(($f['category'] ?? '') === $c)>{{ $c }}</option>@endforeach</select></div>
                <div class="col-sm-5 form-group"><label for="a-act">Action contains</label><input id="a-act" name="action" class="form-control" value="{{ $f['action'] ?? '' }}"></div>
                <div class="col-sm-3 form-group"><label>&nbsp;</label><button class="btn btn-primary btn-block" type="submit">Filter</button></div>
            </div></div></form>
            <div class="box"><div class="box-body no-padding">
                <table class="table table-condensed table-hover"><caption class="sr-only">Audit log</caption>
                    <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Subject</th><th scope="col">IP</th></tr></thead><tbody>
                    @foreach ($logs as $l)<tr><td>{{ $l->created_at->format('d M H:i:s') }}</td><td>{{ $l->user?->name ?? 'System' }}</td><td>{{ $l->category }} · {{ str_replace('_', ' ', $l->action) }}</td><td>{{ $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : '' }}</td><td>{{ $l->ip }}</td></tr>@endforeach
                </tbody></table>
            </div><div class="box-footer clearfix">{{ $logs->links() }}</div></div>
        </div>
        @endif
    </div>
@endsection
