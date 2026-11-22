@extends('layouts.app', ['title' => $title, 'breadcrumbs' => ['Users' => route('admin.users'), $title => '#']])
@section('content')
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="box" novalidate>
        @csrf @if ($user->exists) @method('PUT') @endif
        <div class="box-body">
            @if ($errors->any())<div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div>@endif
            <div class="row">
                <div class="col-md-4 form-group @error('name') has-error @enderror"><label for="name">Full name</label><input id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>@error('name')<span class="help-block">{{ $message }}</span>@enderror</div>
                <div class="col-md-4 form-group @error('email') has-error @enderror"><label for="email">Email address</label><input id="email" type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>@error('email')<span class="help-block">{{ $message }}</span>@enderror</div>
                <div class="col-md-4 form-group"><label for="job_title">Job title</label><input id="job_title" name="job_title" class="form-control" value="{{ old('job_title', $user->job_title) }}"></div>
            </div>
            <div class="row">
                <div class="col-md-4 form-group @error('role') has-error @enderror"><label for="role">Role</label>
                    <select id="role" name="role" class="form-control" required>@foreach ($roles as $r)<option value="{{ $r }}" @selected(old('role', $user->getRoleNames()->first()) === $r)>{{ ucfirst(str_replace('_', ' ', $r)) }}</option>@endforeach</select></div>
                <div class="col-md-8 form-group @error('areas') has-error @enderror"><label for="areas">Area(s) of responsibility</label>
                    <select id="areas" name="areas[]" class="form-control ow-select2" multiple required data-placeholder="Choose areas">
                        <optgroup label="Country, regions and districts">@foreach ($areas as $a)<option value="{{ $a->id }}" @selected(in_array($a->id, old('areas', $user->exists ? $user->orgUnits->pluck('id')->all() : [])))>{{ $a->name }} ({{ $a->type }})</option>@endforeach</optgroup>
                        <optgroup label="Reporting facilities">@foreach ($facilities as $f)<option value="{{ $f->id }}" @selected(in_array($f->id, old('areas', $user->exists ? $user->orgUnits->pluck('id')->all() : [])))>{{ $f->name }}</option>@endforeach</optgroup>
                    </select>
                    <span class="help-block">A user sees only data inside these areas. National roles are assigned to the country.</span>@error('areas')<span class="help-block">{{ $message }}</span>@enderror</div>
            </div>
            @if ($user->exists)
                <div class="checkbox"><label><input type="checkbox" name="active" value="1" @checked(old('active', $user->active))> Account is active</label></div>
                <div class="checkbox"><label><input type="checkbox" name="reset_password" value="1"> Generate a new temporary password</label></div>
            @else
                <p class="text-muted">A temporary password is generated and shown once after saving. Ask the user to change it after first sign-in.</p>
            @endif
        </div>
        <div class="box-footer"><button class="btn btn-primary" type="submit">Save user</button> <a class="btn btn-default" href="{{ route('admin.users') }}">Cancel</a></div>
    </form>
@endsection
