@extends('layouts.app', ['title' => $unit->name, 'subtitle' => ucfirst($unit->type)])

@section('content')
<nav aria-label="Breadcrumb">
    <ol class="breadcrumb ow-breadcrumb">
        @foreach ($ancestors as $a)
            <li><a href="{{ route('areas.show', $a) }}">{{ $a->name }}</a></li>
        @endforeach
        <li class="active" aria-current="page">{{ $unit->name }}</li>
    </ol>
</nav>

<section class="box box-primary" aria-labelledby="unit-heading">
    <header class="box-header with-border"><h2 class="box-title" id="unit-heading">Area profile</h2></header>
    <div class="box-body">
        <dl class="ow-dl">
            <dt>Name</dt><dd>{{ $unit->name }}</dd>
            <dt>Type</dt><dd>{{ $unit->type }}</dd>
            <dt>Code</dt><dd>{{ $unit->code }}</dd>
            <dt>Areas below</dt><dd>{{ $childCount }}</dd>
        </dl>
        @if ($children->isNotEmpty())
            <h3 class="ow-h3">Areas inside {{ $unit->name }}</h3>
            <ul class="ow-list">
                @foreach ($children as $child)
                    <li><a href="{{ route('areas.show', $child) }}">{{ $child->name }}</a></li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
@endsection
