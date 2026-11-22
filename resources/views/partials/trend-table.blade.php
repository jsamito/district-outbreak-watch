<table @isset($tableId) id="{{ $tableId }}" @endisset class="table table-hover table-condensed">
    <caption class="sr-only">Weekly cases with population and incidence</caption>
    <thead><tr><th scope="col">Week starting</th><th scope="col" class="text-right">Cases</th><th scope="col" class="text-right">Population</th><th scope="col" class="text-right">Per 100,000</th><th scope="col" class="text-right">Expected</th><th scope="col">Notes</th></tr></thead>
    <tbody>
    @foreach ($rows as $r)
        <tr>
            <td data-order="{{ $r->week }}">{{ $r->week }}</td>
            <td class="text-right">{{ number_format($r->cases) }}</td>
            <td class="text-right">{{ $r->population ? number_format($r->population) : 'n/a' }}</td>
            <td class="text-right">{{ $r->incidence !== null ? number_format($r->incidence, 2) : 'n/a' }}</td>
            <td class="text-right">{{ $r->expected !== null ? number_format($r->expected, 1) : '' }}</td>
            <td>@if ($r->signal)<span class="ow-sig sev-high">Above threshold</span>@endif @if ($r->cases > 0 && $r->cases < config('surveillance.small_number_floor'))<span class="ow-flag">Small number</span>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
