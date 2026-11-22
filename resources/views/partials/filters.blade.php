{{-- Shared filter bar. Expects: $filters, $areaOptions, $facilityOptions, $diseases (may be empty), optional $showDates, $extra (HTML) --}}
@php
    $svRoute = \Illuminate\Support\Facades\Route::currentRouteName();
    $svRoute = array_key_exists($svRoute, \App\Models\SavedView::ROUTES) ? $svRoute : null;
    $svViews = $svRoute ? \App\Models\SavedView::where(['user_id' => auth()->id(), 'route' => $svRoute])->orderBy('name')->get() : null;
@endphp
<form method="GET" action="{{ url()->current() }}" class="ow-filters box" aria-label="Filters">
    <div class="box-body">
        <div class="row">
            <div class="col-sm-6 col-md-3 form-group">
                <label for="f-area">Area</label>
                <select id="f-area" name="area" class="form-control ow-select2" data-autosubmit="area">
                    @foreach ($areaOptions as $o)
                        <option value="{{ $o->id }}" @selected($o->id == $filters['area'])>{{ $o->type === 'district' ? '— ' : ($o->type === 'region' ? '' : '') }}{{ $o->name }}{{ $o->type === 'region' ? ' region' : '' }}</option>
                    @endforeach
                </select>
            </div>
            @if ($facilityOptions->isNotEmpty())
            <div class="col-sm-6 col-md-3 form-group">
                <label for="f-facility">Facility</label>
                <select id="f-facility" name="facility" class="form-control ow-select2" data-allow-clear="true" data-placeholder="All facilities">
                    <option value="">All facilities</option>
                    @foreach ($facilityOptions as $f)
                        <option value="{{ $f->id }}" @selected($f->id == $filters['facility'])>{{ $f->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @if ($diseases->isNotEmpty())
            <div class="col-sm-6 col-md-2 form-group">
                <label for="f-disease">Disease</label>
                <select id="f-disease" name="disease" class="form-control ow-select2" data-autosubmit="1">
                    @if ($allDiseases ?? false)<option value="">All diseases</option>@endif
                    @foreach ($diseases as $d)
                        <option value="{{ $d->code }}" @selected($d->code === $filters['disease'])>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @isset($extra){!! $extra !!}@endisset
            @if (($showDates ?? true))
            <div class="col-xs-6 col-md-2 form-group">
                <label for="f-from">From (week of)</label>
                <input id="f-from" type="text" name="from" class="form-control ow-datepicker" value="{{ $filters['from'] }}" autocomplete="off" inputmode="numeric" placeholder="yyyy-mm-dd">
            </div>
            <div class="col-xs-6 col-md-2 form-group">
                <label for="f-to">To (week of)</label>
                <input id="f-to" type="text" name="to" class="form-control ow-datepicker" value="{{ $filters['to'] }}" autocomplete="off" inputmode="numeric" placeholder="yyyy-mm-dd">
            </div>
            @endif
            <div class="ow-filter-actions">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter" aria-hidden="true"></i> Apply filters</button>
                <a href="{{ url()->current() }}" class="btn btn-default btn-sm">Reset</a>
                @if ($svRoute && $svViews !== null)
                <details class="ow-views"><summary class="btn btn-default btn-sm"><i class="fa fa-bookmark-o" aria-hidden="true"></i> Views</summary>
                    <div class="ow-views__panel">
                        @forelse ($svViews as $v)<div class="ow-views__row"><a href="{{ $v->url() }}">{{ $v->name }}</a><button type="submit" form="sv-del-{{ $v->id }}" class="btn btn-link btn-xs" aria-label="Remove saved view {{ $v->name }}"><i class="fa fa-times" aria-hidden="true"></i></button></div>
                        @empty<p class="text-muted">No saved views yet.</p>@endforelse
                        <hr>
                        <label for="sv-name">Save the current filters as</label>
                        <input id="sv-name" form="sv-save" name="name" class="form-control input-sm" maxlength="80" placeholder="e.g. Kasese cholera" required>
                        <label class="checkbox-inline"><input type="checkbox" form="sv-save" name="keep_dates" value="1"> Keep the dates</label>
                        <button type="submit" form="sv-save" class="btn btn-primary btn-xs">Save view</button>
                    </div>
                </details>
                @endif
            </div>
        </div>
    </div>
</form>
@if ($svRoute && $svViews !== null)
<form id="sv-save" method="POST" action="{{ route('saved-views.store') }}">@csrf
    <input type="hidden" name="route" value="{{ $svRoute }}">
    @foreach (['area', 'facility', 'disease', 'from', 'to'] as $k)@if (! empty($filters[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}">@endif @endforeach
</form>
@foreach ($svViews as $v)<form id="sv-del-{{ $v->id }}" method="POST" action="{{ route('saved-views.destroy', $v) }}">@csrf @method('DELETE')</form>@endforeach
@endif
