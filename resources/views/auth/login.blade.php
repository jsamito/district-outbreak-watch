<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/AdminLTE.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="hold-transition login-page">
<a class="ow-skip-link" href="#main">Skip to the sign-in form</a>
<div class="login-box">
    <div class="login-logo">
        <a href="{{ route('home') }}"><strong>Outbreak</strong>Watch</a>
    </div>
    <main id="main" class="login-box-body" tabindex="-1">
        <h1 class="login-box-msg ow-login-title">Sign in to your account</h1>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" novalidate>
            @csrf
            <div class="form-group">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}"
                       autocomplete="username" autofocus required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <input id="password" name="password" type="password" class="form-control"
                           autocomplete="current-password" required>
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-default" id="toggle-password" aria-controls="password" aria-pressed="false">Show</button>
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Sign in</button>
        </form>

        <p class="ow-login-note">
            Access is limited to authorised surveillance staff. Sessions end after a period of inactivity.
        </p>
    </main>
</div>
<script>
    (function () {
        var btn = document.getElementById('toggle-password');
        var input = document.getElementById('password');
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    })();
</script>
</body>
</html>
