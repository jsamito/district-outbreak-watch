@extends('layouts.app', ['title' => 'Users'])
@section('actions')<a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus" aria-hidden="true"></i> New user</a>@endsection
@section('content')
    <div class="box"><div class="box-body no-padding">
        <table class="table table-hover">
            <caption class="sr-only">Users</caption>
            <thead><tr><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Area of responsibility</th><th scope="col">Last sign-in</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Edit</span></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td><strong>{{ $u->name }}</strong><small class="text-muted" style="display:block">{{ $u->email }}</small></td>
                    <td>{{ $u->getRoleNames()->map(fn ($r) => str_replace('_', ' ', $r))->join(', ') }}</td>
                    <td>{{ $u->orgUnits->pluck('name')->join(', ') }}</td>
                    <td>{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
                    <td><span class="badge badge-{{ $u->active ? 'completed' : 'muted' }}">{{ $u->active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-right"><a class="btn btn-default btn-xs" style="width:auto" href="{{ route('admin.users.edit', $u) }}">Edit<span class="sr-only"> {{ $u->name }}</span></a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
@endsection
