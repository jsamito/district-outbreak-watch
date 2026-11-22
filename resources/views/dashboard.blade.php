@extends('layouts.app', ['title' => 'Overview'])

@section('subtitle', $area->name.' · latest week starting '.$latest->format('d M Y'))

@section('actions')
    <form method="GET" action="{{ route('dashboard') }}" class="form-inline" aria-label="Choose area">
        <input type="hidden" name="disease" value="{{ $disease->code }}">
        <label for="ov-area" class="sr-only">Area</label>
        <select id="ov-area" name="area" class="form-control input-sm ow-select2" data-autosubmit="1">
            @foreach ($areaChoices as $o)
                <option value="{{ $o->id }}" @selected($o->id == $area->id)>{{ $o->name }}@if ($o->type === 'region') region @endif</option>
            @endforeach
        </select>
        <noscript><button class="btn btn-default btn-sm" type="submit">Go</button></noscript>
    </form>
@endsection

@section('content')
    <section aria-label="Headline numbers" class="ow-strip" data-live-area="{{ $area->id }}" data-live-url="{{ route('live.summary') }}">
        @foreach ($strip as $s)
            <div data-live-disease="{{ $s->disease->code }}">
                <div class="s-label"><i class="fa fa-stethoscope" aria-hidden="true"></i> {{ $s->disease->name }}</div>
                <div class="s-num">{{ number_format($s->cases) }}
                    @if ($s->delta !== null)
                        <span class="s-delta {{ $s->delta > 0 ? 'bad' : 'good' }}"><span aria-hidden="true">{{ $s->delta > 0 ? '▲' : '▼' }}</span> {{ abs($s->delta) }}%<span class="sr-only"> {{ $s->delta > 0 ? 'increase' : 'decrease' }} on the previous week</span></span>
                    @endif
                </div>
                <div class="s-hint">cases last full week @if ($s->cases > 0 && $s->cases < config('surveillance.small_number_floor'))· <span class="ow-flag">small number</span>@endif<br><span class="ow-live" aria-live="polite"><span data-live-count>{{ number_format($s->live) }}</span> so far this week</span></div>
            </div>
        @endforeach
        <div>
            <div class="s-label"><i class="fa fa-check-square-o" aria-hidden="true"></i> Reporting</div>
            <div class="s-num">{{ $reporting !== null ? number_format($reporting, 0).'%' : 'n/a' }}</div>
            <div class="s-hint">of expected weekly reports received</div>
        </div>
    </section>

    <div x-data="overview" data-url="{{ route('dashboard.data') }}" data-area="{{ $area->id }}">
    <div class="ow-chips" role="group" aria-label="Disease shown in the charts">
        @foreach ($diseases as $d)
            <a href="{{ route('dashboard', ['disease' => $d->code, 'area' => $area->id]) }}" data-code="{{ $d->code }}" data-name="{{ $d->name }}" @click="select" class="ow-chip {{ $d->id === $disease->id ? 'active' : '' }}" @if ($d->id === $disease->id) aria-current="true" @endif>{{ $d->name }}</a>
        @endforeach
        <i class="fa fa-spinner fa-spin ow-spin" aria-hidden="true" x-show="loading" x-cloak></i><span class="sr-only" role="status" x-text="status"></span>
    </div>

    <div class="row ow-eq">
        <div class="col-md-8">
            <x-chart-card id="chart-trend" title="{{ $disease->name }}: weekly cases, last 52 weeks" icon="fa-line-chart" :height="300"
                :summary="'Weekly cases of '.$disease->name.' in '.$area->name.'. Diamonds mark weeks above the outbreak signal threshold (baseline mean plus three standard deviations, at least '.config('surveillance.small_number_floor').' cases). The axis starts at zero.'">
                <x-slot:actions><a href="{{ route('trends', ['disease' => $disease->code, 'area' => $area->id]) }}" class="btn btn-box-tool">Open trends</a></x-slot:actions>
                <x-slot:table>
                    <table class="table table-condensed"><thead><tr><th>Week starting</th><th class="text-right">Cases</th><th>Signal</th></tr></thead><tbody id="trend-tbody">
                        @foreach (array_reverse(array_keys($trend['labels'])) as $i)
                            <tr><td>{{ $trend['labels'][$i] }}</td><td class="text-right">{{ number_format($trend['cases'][$i]) }}</td><td>{{ $trend['signals'][$i] ? 'Above threshold' : '' }}</td></tr>
                        @endforeach
                    </tbody></table>
                </x-slot:table>
            </x-chart-card>
        </div>
        <div class="col-md-4">
            <div class="box">
                <div class="box-header with-border"><h2 class="box-title"><i class="fa fa-bell-o" aria-hidden="true"></i> Needs attention <span class="badge badge-muted">{{ $attention->count() }}</span></h2>
                    @if (Route::has('alerts.index'))<div class="box-tools pull-right"><a href="{{ route('alerts.index') }}" class="btn btn-box-tool">All alerts</a></div>@endif</div>
                @if ($attention->isEmpty())
                    <div class="ow-empty" style="padding:28px"><i class="fa fa-check-circle text-green" aria-hidden="true"></i><p>No area is above its outbreak threshold this week.</p></div>
                @else
                    <ul class="ow-attn">
                        @foreach ($attention as $a)
                            <li><a href="{{ route('trends', ['disease' => \Illuminate\Support\Str::slug($a->disease), 'area' => $a->unit_id]) }}">
                                <span class="a-ic"><i class="fa {{ $a->severity === 'high' ? 'fa-exclamation-circle' : 'fa-line-chart' }}" aria-hidden="true"></i></span>
                                <span class="a-main">{{ $a->disease }} · {{ $a->unit }}
                                    <small>@if ($a->immediate) Immediately notifiable: {{ number_format($a->observed) }} case(s) @else {{ number_format($a->observed) }} cases against {{ number_format($a->expected, 1) }} expected @endif</small></span>
                                <span class="ow-sig sev-{{ $a->severity }}">{{ ucfirst($a->severity) }}</span></a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="row ow-eq">
        <div class="col-md-6">
            <x-chart-card id="chart-top" :title="$disease->name.': '.($rank === 'incidence' ? 'highest incidence' : 'most cases').' in the last 4 weeks'" icon="fa-bar-chart" :height="300"
                :summary="'Areas below '.$area->name.' ranked by '.($rank === 'incidence' ? 'cases per 100,000 people' : 'number of cases').' over the last four weeks. The axis starts at zero.'">
                <x-slot:table>
                    <table class="table table-condensed"><thead><tr><th>Area</th><th class="text-right">Cases</th><th class="text-right">Population</th><th class="text-right">Per 100,000</th></tr></thead><tbody id="top-tbody">
                        @foreach ($top as $t)<tr><td>{{ $t->name }}</td><td class="text-right">{{ number_format($t->cases) }}</td><td class="text-right">{{ $t->population ? number_format($t->population) : 'n/a' }}</td><td class="text-right">{{ $t->incidence !== null ? number_format($t->incidence, 1) : 'n/a' }}</td></tr>@endforeach
                    </tbody></table>
                </x-slot:table>
            </x-chart-card>
        </div>
        <div class="col-md-6">
            <x-chart-card id="chart-reporting" title="Reporting completeness, last 26 weeks" icon="fa-check-square-o" :height="300"
                summary="Share of expected weekly reports received from reporting facilities. Higher is better; the axis runs from 0 to 100 percent.">
            </x-chart-card>
        </div>
    </div>

    </div>{{-- /overview --}}

    <div class="row ow-eq">
        <div class="col-md-12">
            <x-chart-card id="chart-vacc" title="Vaccination coverage, last 12 months" icon="fa-medkit" :height="260"
                summary="Doses given divided by the expected number of children under one year, by vaccine, against the target. Values above 100% indicate a data-quality problem (numerator or population).">
                <x-slot:actions><a href="{{ route('vaccination', ['area' => $area->id]) }}" class="btn btn-box-tool">Open vaccination</a></x-slot:actions>
            </x-chart-card>
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
    var floor = @js(config('surveillance.small_number_floor'));
    var charts = {};
    // Latest data for the ranking chart, so it is correct even if the disease is switched before it scrolls into view.
    window.owPanel = { rank: @js($rank), top: @js($top->map(fn ($t) => ['name' => $t->name, 'cases' => (float) $t->cases, 'incidence' => $t->incidence === null ? null : round($t->incidence, 1)])->values()) };
    charts.trend = owCharts.line('chart-trend', { labels: @js($trend['labels']), yTitle: 'Cases per week', floor: floor, ariaLabel: @js('Weekly cases of '.$disease->name),
        series: [{ label: 'Weekly cases', data: @js($trend['cases']), signals: @js($trend['signals']) },
                 { label: 'Signal threshold', data: @js($trend['upper']), color: '#6b7682', dashed: true }], points: true });
    owCharts.lazy('chart-top', function () {
        var p = window.owPanel, inc = p.rank === 'incidence';
        charts.top = owCharts.bar('chart-top', { horizontal: true, labels: p.top.map(function (t) { return t.name; }), yTitle: inc ? 'Cases per 100,000 people' : 'Cases', integer: !inc, floor: floor,
            ariaLabel: 'Areas ranked by ' + p.rank, series: [{ label: inc ? 'Per 100,000' : 'Cases', data: p.top.map(function (t) { return inc ? t.incidence : t.cases; }) }] });
    });
    window.owOverviewCharts = charts;
    owCharts.lazy('chart-reporting', function () { owCharts.line('chart-reporting', { labels: @js(array_keys($reportTrend)), yTitle: 'Reports received (%)', yMax: 100, integer: false, unit: '%', ariaLabel: 'Reporting completeness by week',
        series: [{ label: 'Completeness', data: @js(array_map(fn ($v) => round($v, 1), array_values($reportTrend))) }] }); });
    var vacc = @js($vacc);
    owCharts.lazy('chart-vacc', function () { owCharts.bar('chart-vacc', { labels: vacc.map(function (v) { return v.code; }), yTitle: 'Coverage (%)', yMax: 110, integer: false, unit: '%', ariaLabel: 'Vaccination coverage by vaccine',
        series: [{ label: 'Coverage', data: vacc.map(function (v) { return v.coverage; }) },
                 { label: 'Target', data: vacc.map(function (v) { return v.target; }), type: 'line', color: '#d55e00', order: 0 }] }); });
}());
</script>
@endpush

@push('scripts')
<script @nonce>
/* Live updates start after the page has loaded and the browser is idle. */
owAfterLoad(function () { var s = document.createElement('script'); s.src = @js(asset_ver('js/live.js')); document.head.appendChild(s); });
</script>
<script @nonce>
/* Alpine.js is loaded the first time someone points at, focuses, touches or clicks the disease chips,
   so the page itself stays light. The chips are ordinary links, so they still work if it is not loaded yet. */
(function () {
    var root = document.querySelector('[x-data="overview"]');
    if (!root) { return; }
    var src = @js(asset_ver('vendor/alpine/alpine.csp.min.js'));
    var pending = null;
    function load(done) {
        if (window.Alpine) { if (done) { done(); } return; }
        if (!pending) {
            pending = new Promise(function (ok) { var s = document.createElement('script'); s.src = src; s.defer = true; s.onload = function () { setTimeout(ok, 30); }; document.head.appendChild(s); });
        }
        if (done) { pending.then(done); }
    }
    ['pointerenter', 'focusin', 'touchstart'].forEach(function (ev) { root.addEventListener(ev, function () { load(); }, { once: true, passive: true, capture: true }); });
    root.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('.ow-chip') : null;
        if (a && !window.Alpine) { e.preventDefault(); e.stopPropagation(); load(function () { a.click(); }); }
    }, true);
}());
</script>
@endpush

@push('alpine')
<script @nonce>
document.addEventListener('alpine:init', function () {
    var esc = function (t) { return String(t === null || t === undefined ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
    var num = function (v, d) { return v === null || v === undefined ? 'n/a' : Number(v).toLocaleString(undefined, { maximumFractionDigits: d || 0 }); };
    Alpine.data('overview', function () {
        return {
            loading: false,
            status: '',
            select: function (event) {
                event.preventDefault();
                var link = event.currentTarget;
                var root = this.$root;
                var self = this;
                var code = link.getAttribute('data-code');
                var name = link.getAttribute('data-name');
                self.loading = true;
                self.status = 'Loading ' + name;
                var url = root.getAttribute('data-url') + '?disease=' + encodeURIComponent(code) + '&area=' + encodeURIComponent(root.getAttribute('data-area'));
                fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
                    .then(function (d) { self.apply(root, d, link); })
                    .catch(function () { window.location = link.getAttribute('href'); });
            },
            apply: function (root, d, link) {
                var c = window.owOverviewCharts;
                window.owPanel = { rank: d.rank, top: d.top };
                var floor = @js(config('surveillance.small_number_floor'));
                c.trend.data.labels = d.trend.labels;
                var ds = c.trend.data.datasets;
                ds[0].data = d.trend.cases;
                ds[0].pointStyle = d.trend.signals.map(function (f) { return f ? 'rectRot' : 'circle'; });
                ds[0].pointRadius = d.trend.signals.map(function (f) { return f ? 7 : 2; });
                ds[0].pointBackgroundColor = d.trend.signals.map(function (f) { return f ? '#d55e00' : '#0072b2'; });
                ds[0].pointBorderColor = d.trend.signals.map(function (f) { return f ? '#7a2f00' : '#0072b2'; });
                ds[1].data = d.trend.upper;
                c.trend.update();
                var isInc = d.rank === 'incidence';
                if (c.top) {
                    c.top.data.labels = d.top.map(function (t) { return t.name; });
                    c.top.data.datasets[0].data = d.top.map(function (t) { return isInc ? t.incidence : t.cases; });
                    c.top.data.datasets[0].label = isInc ? 'Per 100,000' : 'Cases';
                    c.top.options.scales.x.title.text = isInc ? 'Cases per 100,000 people' : 'Cases';
                    c.top.update();
                }

                document.getElementById('chart-trend-title').textContent = d.disease.name + ': weekly cases, last 52 weeks';
                document.getElementById('chart-top-title').textContent = d.disease.name + ': ' + (isInc ? 'highest incidence' : 'most cases') + ' in the last 4 weeks';
                var cap = document.getElementById('chart-trend-caption');
                cap.textContent = 'Weekly cases of ' + d.disease.name + '. Diamonds mark weeks above the outbreak signal threshold (baseline mean plus three standard deviations, at least ' + floor + ' cases). The axis starts at zero.';
                document.getElementById('chart-trend').setAttribute('aria-label', 'Weekly cases of ' + d.disease.name);

                var tb = document.getElementById('trend-tbody');
                if (tb) { tb.innerHTML = d.trend.labels.map(function (w, i) { return i; }).reverse().map(function (i) { return '<tr><td>' + esc(d.trend.labels[i]) + '</td><td class="text-right">' + num(d.trend.cases[i]) + '</td><td>' + (d.trend.signals[i] ? 'Above threshold' : '') + '</td></tr>'; }).join(''); }
                var tt = document.getElementById('top-tbody');
                if (tt) { tt.innerHTML = d.top.map(function (t) { return '<tr><td>' + esc(t.name) + '</td><td class="text-right">' + num(t.cases) + '</td><td class="text-right">' + (t.population ? num(t.population) : 'n/a') + '</td><td class="text-right">' + (t.incidence !== null ? num(t.incidence, 1) : 'n/a') + '</td></tr>'; }).join(''); }
                var more = document.querySelector('#chart-trend-title').closest('.box').querySelector('.btn-box-tool');
                if (more) { more.setAttribute('href', d.trendsUrl); }

                root.querySelectorAll('.ow-chip').forEach(function (a) { var on = a === link; a.classList.toggle('active', on); if (on) { a.setAttribute('aria-current', 'true'); } else { a.removeAttribute('aria-current'); } });
                history.replaceState(null, '', link.getAttribute('href'));
                this.loading = false;
                this.status = d.disease.name + ' charts updated';
            }
        };
    });
});
</script>
@endpush
