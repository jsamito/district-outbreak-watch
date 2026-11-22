@extends('layouts.app', ['title' => $title, 'breadcrumbs' => ['Cases' => route('cases.index'), $title => '#']])
@section('subtitle', $subtitle)

@section('content')
    @if ($case->is_synthetic)<div class="ow-notice"><i class="fa fa-info-circle" aria-hidden="true"></i> This is a <strong>synthetic</strong> record generated for testing. It does not describe a real person.</div>@endif
    @php $age = $case->person->ageOn($case->notification_date); @endphp

    <section class="ow-strip" aria-label="Case summary">
        <div><div class="s-label">Classification</div><div class="s-num" style="font-size:18px">{{ ucfirst($case->classification) }}</div><div class="s-hint">{{ $case->disease->name }} (ICD-10 {{ $case->icd10_code }})</div></div>
        <div><div class="s-label">Outcome</div><div class="s-num" style="font-size:18px">{{ ucfirst($case->outcome) }}</div><div class="s-hint">{{ $case->hospitalised ? 'Admitted to hospital' : 'Outpatient' }}</div></div>
        <div><div class="s-label">Investigation</div><div class="s-num" style="font-size:18px">{{ ucfirst($case->status) }}</div><div class="s-hint">{{ $full && $case->investigation_date ? 'Investigated '.$case->investigation_date->format('d M Y') : 'Not yet investigated' }}</div></div>
        <div><div class="s-label">{{ $full ? 'Notified' : 'Week of' }}</div><div class="s-num" style="font-size:18px">{{ $full ? $case->notification_date->format('d M Y') : $case->epi_week->format('d M Y') }}</div><div class="s-hint">{{ $full ? $case->facility->name : ($case->residence->name ?? '') }}</div></div>
    </section>

    <div class="row ow-eq">
        <div class="col-md-5">
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-id-card-o" aria-hidden="true"></i> Person</h3></div>
                <div class="box-body">
                    <dl class="ow-dl">
                        <dt>Reference</dt><dd><code>{{ $case->person->pseudonym }}</code></dd>
                        <dt>Sex</dt><dd>{{ ['M' => 'Male', 'F' => 'Female', 'U' => 'Unknown'][$case->person->sex] }}</dd>
                        <dt>{{ $full ? 'Age' : 'Age group' }}</dt><dd>{{ $full ? $age.' years' : \App\Models\Person::ageGroup($age) }}</dd>
                        <dt>District of residence</dt><dd>{{ $case->residence->name ?? 'n/a' }}</dd>
                        @if ($full)
                            <dt>Onset</dt><dd>{{ $case->onset_date->format('d M Y') }}</dd>
                            <dt>Reporting facility</dt><dd>{{ $case->facility->name }}</dd>
                        @endif
                    </dl>
                    <p class="text-muted" style="margin-top:10px">No name, address or phone number is stored.</p>
                </div>
            </div>
            @if ($full)
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-medkit" aria-hidden="true"></i> Vaccination history</h3></div>
                <div class="box-body">
                    @forelse ($vaccinations as $v)<p>{{ $v->antigen->name }}, dose {{ $v->dose_number }}, {{ $v->administered_on->format('d M Y') }}</p>@empty<p class="text-muted">No vaccination recorded.</p>@endforelse
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-7">
            @if ($canEdit)
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-pencil" aria-hidden="true"></i> Update investigation</h3></div>
                <form method="POST" action="{{ route('cases.update', $case) }}" class="box-body">
                    @csrf @method('PUT')
                    <div class="row">
                        <div class="col-sm-4 form-group"><label for="u-class">Classification</label><select id="u-class" name="classification" class="form-control">@foreach (\App\Models\CaseRecord::CLASSIFICATIONS as $c)<option value="{{ $c }}" @selected($case->classification === $c)>{{ ucfirst($c) }}</option>@endforeach</select></div>
                        <div class="col-sm-4 form-group"><label for="u-out">Outcome</label><select id="u-out" name="outcome" class="form-control">@foreach (\App\Models\CaseRecord::OUTCOMES as $c)<option value="{{ $c }}" @selected($case->outcome === $c)>{{ ucfirst($c) }}</option>@endforeach</select></div>
                        <div class="col-sm-4 form-group"><label for="u-status">Investigation</label><select id="u-status" name="status" class="form-control">@foreach (\App\Models\CaseRecord::STATUSES as $c)<option value="{{ $c }}" @selected($case->status === $c)>{{ ucfirst($c) }}</option>@endforeach</select></div>
                    </div>
                    <div class="form-group" style="max-width:240px"><label for="u-date">Investigation date</label><input id="u-date" type="text" name="investigation_date" class="form-control ow-datepicker" value="{{ $case->investigation_date?->toDateString() }}" autocomplete="off"></div>
                    <button class="btn btn-primary" type="submit">Save changes</button>
                    <p class="help-block">Marking a case as discarded removes it from the weekly counts.</p>
                </form>
            </div>
            @endif

            <div class="box">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-flask" aria-hidden="true"></i> Laboratory</h3></div>
                <div class="box-body no-padding">
                    <table class="table table-condensed"><thead><tr><th scope="col">Test</th><th scope="col">Specimen</th><th scope="col">Collected</th><th scope="col">Result</th></tr></thead><tbody>
                        @forelse ($case->labResults as $l)<tr><td>{{ $l->test_code }}</td><td>{{ $l->specimen_type }}</td><td>{{ $full ? $l->collected_at->format('d M Y') : 'n/a' }}</td><td>{{ ucfirst($l->result) }}</td></tr>@empty<tr><td colspan="4" class="text-muted">No laboratory tests.</td></tr>@endforelse
                    </tbody></table>
                </div>
                @if ($canLab)
                <form method="POST" action="{{ route('cases.lab', $case) }}" class="box-footer">
                    @csrf
                    <div class="row">
                        <div class="col-sm-4 form-group"><label for="l-test">Test</label><input id="l-test" name="test_code" class="form-control" required></div>
                        <div class="col-sm-4 form-group"><label for="l-spec">Specimen</label><input id="l-spec" name="specimen_type" class="form-control" required></div>
                        <div class="col-sm-4 form-group"><label for="l-date">Collected</label><input id="l-date" type="text" name="collected_at" class="form-control ow-datepicker" value="{{ now()->toDateString() }}" autocomplete="off" required></div>
                    </div>
                    <button class="btn btn-default btn-sm" type="submit">Record test as pending</button>
                </form>
                @endif
            </div>

            <div class="box">
                <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-history" aria-hidden="true"></i> Timeline</h3></div>
                <ul class="ow-list">
                    @foreach ($case->events->sortByDesc('occurred_at') as $e)
                        <li><i class="fa fa-circle-o text-muted" aria-hidden="true"></i>
                            <span class="ow-list-main">{{ ['notified' => 'Notified', 'classified' => 'Classification changed', 'outcome' => 'Outcome recorded', 'investigated' => 'Investigation updated', 'lab_result' => 'Laboratory', 'note' => 'Note'][$e->type] ?? $e->type }}
                                @if ($e->type === 'note' && $full)<small style="color:#1f2933">{{ $e->data['text'] ?? '' }}</small>@elseif ($e->type === 'classified')<small>{{ ucfirst($e->data['classification'] ?? '') }}</small>@elseif ($e->type === 'outcome')<small>{{ ucfirst($e->data['outcome'] ?? '') }}</small>@endif
                                <small>{{ $full ? $e->occurred_at->format('d M Y H:i') : 'Week of '.$e->occurred_at->startOfWeek()->format('d M Y') }}@if ($full && $e->user) · {{ $e->user->name }}@endif</small></span></li>
                    @endforeach
                </ul>
                @if ($canEdit)
                <form method="POST" action="{{ route('cases.note', $case) }}" class="box-footer">
                    @csrf
                    <label for="n-note" class="sr-only">Add a note</label>
                    <div class="input-group"><input id="n-note" name="note" class="form-control" maxlength="500" placeholder="Add a note to the timeline" required><span class="input-group-btn"><button class="btn btn-default" type="submit">Add</button></span></div>
                </form>
                @endif
            </div>
        </div>
    </div>
@endsection
