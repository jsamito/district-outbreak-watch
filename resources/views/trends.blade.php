@extends('layouts.app', ['title' => 'Trends'])
@section('subtitle', $subtitle)

@section('content')
    @include('partials.filters')

    <x-chart-card id="chart-trend" :title="$disease->name.': weekly cases'" :height="340"
        :summary="'Weekly '.$disease->name.' cases in '.$area->name.' from '.$from->format('d M Y').' to '.$to->format('d M Y').' ('.number_format($total).' cases in total). The dotted line is the same weeks one year earlier; the dashed line is a four-week moving average; diamonds are weeks above the outbreak threshold. The axis starts at zero.'">
        <x-slot:table>
            @include('partials.trend-table')
        </x-slot:table>
    </x-chart-card>

    <div class="box">
        <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-sticky-note-o" aria-hidden="true"></i> Notes on this chart</h2></div>
        <div class="box-body">
            @forelse ($annotations as $n)
                <p style="margin:0 0 8px"><strong>Week of {{ $n->period_start->format('d M Y') }}:</strong> {{ $n->text }} <small class="text-muted">{{ $n->user->name }}</small>
                    @if ($n->user_id === auth()->id())<form method="POST" action="{{ route('annotations.destroy', $n) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-link btn-xs" type="submit">Remove<span class="sr-only"> note of {{ $n->period_start->format('d M Y') }}</span></button></form>@endif</p>
            @empty
                <p class="text-muted">No notes yet for these weeks.</p>
            @endforelse
            @can('annotate charts')
            <form method="POST" action="{{ route('annotations.store') }}" class="form-inline" style="margin-top:10px">
                @csrf
                <input type="hidden" name="org_unit_id" value="{{ $area->id }}"><input type="hidden" name="disease" value="{{ $disease->code }}">
                <label class="sr-only" for="an-week">Week</label><input id="an-week" name="period_start" type="text" class="form-control input-sm ow-datepicker" value="{{ $to->toDateString() }}" autocomplete="off" style="width:120px">
                <label class="sr-only" for="an-text">Note</label><input id="an-text" name="text" class="form-control input-sm" maxlength="500" required placeholder="What happened, e.g. vaccination campaign started" style="width:min(420px,100%)">
                <button class="btn btn-default btn-sm" type="submit">Add note</button>
                @error('text')<span class="text-danger">{{ $message }}</span>@enderror
            </form>
            @endcan
        </div>
    </div>

    <div class="box">
        <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-table" aria-hidden="true"></i> Weekly values with denominators</h2></div>
        <div class="box-body no-padding">
            @include('partials.trend-table', ['tableId' => 'trend-table', 'datatable' => true])
        </div>
    </div>
@endsection

@push('plugins')
<script src="{{ asset_ver('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset_ver('js/charts.js') }}"></script>
@endpush
@push('scripts')
<script @nonce>
(function () {
    owCharts.line('chart-trend', { labels: @js($labels), yTitle: 'Cases per week', floor: @js(config('surveillance.small_number_floor')), ariaLabel: @js('Weekly cases of '.$disease->name.' in '.$area->name),
        series: [{ label: 'Weekly cases', data: @js($cases), signals: @js($signals) },
                 { label: 'Four-week average', data: @js($ma), color: '#009e73', dashed: true },
                 { label: 'Same weeks last year', data: @js($prevYear), color: '#6b7682' }] });
    owRegisterDT('trend-table', { order: [[0, 'desc']], pageLength: 12 });
}());
</script>
@endpush
