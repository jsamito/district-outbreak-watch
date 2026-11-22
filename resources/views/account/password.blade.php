@extends('layouts.app', ['title' => $title, 'breadcrumbs' => ['Account' => '#', 'Change password' => '#']])

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="ow-form" style="max-width:520px" novalidate>
        @csrf @method('PUT')
        <section class="box ow-form__section">
            <div class="box-body">
                @if ($forced)
                    <div class="alert alert-warning" role="alert"><strong>Choose your own password to continue.</strong> You signed in with a temporary password, so nothing else is available until you change it.</div>
                @endif
                <div class="form-group @error('current_password') has-error @enderror">
                    <label for="current_password">{{ $forced ? 'Temporary password' : 'Current password' }}</label>
                    <input id="current_password" type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                    @error('current_password')<span class="help-block">{{ $message }}</span>@enderror
                </div>
                <div class="form-group @error('password') has-error @enderror">
                    <label for="password">New password</label>
                    <input id="password" type="password" name="password" class="form-control" autocomplete="new-password" required minlength="12">
                    <span class="help-block">At least 12 characters with upper and lower case letters and a number. A short sentence works well.</span>
                    @error('password')<span class="help-block">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Repeat the new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
            </div>
            <div class="box-footer"><button class="btn btn-primary" type="submit">Change password</button> @unless ($forced)<a class="btn btn-default" href="{{ route('dashboard') }}">Cancel</a>@endunless</div>
        </section>
    </form>
@endsection
