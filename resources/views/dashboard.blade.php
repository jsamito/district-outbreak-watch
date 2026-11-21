@extends('layouts.app', ['title' => 'Overview'])

@section('content')
<section aria-labelledby="access-heading" class="box box-primary">
    <header class="box-header with-border">
        <h2 class="box-title" id="access-heading">Your access</h2>
    </header>
    <div class="box-body">
        <dl class="ow-dl">
            <dt>Signed in as</dt>
            <dd>{{ $user->name }} ({{ $user->job_title ?: 'no title' }})</dd>
            <dt>Role</dt>
            <dd>{{ $user->getRoleNames()->map(fn ($r) => str_replace('_', ' ', $r))->join(', ') }}</dd>
            <dt>Area of responsibility</dt>
            <dd>
                @forelse ($roots as $root)
                    <a href="{{ route('areas.show', $root) }}">{{ $root->name }}</a> ({{ $root->type }})@if (! $loop->last), @endif
                @empty
                    none assigned
                @endforelse
            </dd>
            <dt>Permissions</dt>
            <dd>{{ $permissions->join(', ') ?: 'none' }}</dd>
        </dl>
    </div>
</section>
@endsection
