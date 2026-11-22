{{-- Shared filter bar. Expects: $filters, $areaOptions, $facilityOptions, $diseases (may be empty), optional $showDates, $extra (HTML) --}}
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
            </div>
        </div>
    </div>
</form>
