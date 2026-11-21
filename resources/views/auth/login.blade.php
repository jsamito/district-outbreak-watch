<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="ow-login">
<a class="ow-skip-link" href="#main">Skip to the sign-in form</a>
<div class="ow-login__shell">
    <div class="ow-login__left">
        <div>
            <div class="ow-login__badge" aria-hidden="true"><i class="fa fa-heartbeat"></i></div>
            <h2>{{ config('app.name') }}</h2>
            <p>Early warning for notifiable diseases: weekly counts, alerts, cases and projections for district and national health teams.</p>
        </div>
        <div class="ow-login__foot"><strong>Disease Surveillance</strong> &nbsp;·&nbsp; Uganda</div>
    </div>
    <main id="main" class="ow-login__right" tabindex="-1">
        <div class="ow-login__form">
            <h1>Welcome back!</h1>
            <p class="ow-login__sub">Sign in to continue to {{ config('app.name') }}.</p>

            @if ($errors->any())
                <div class="ow-login__err" role="alert"><i class="fa fa-exclamation-circle" aria-hidden="true"></i> {{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

                <label for="password">Password</label>
                <div class="ow-login__pw">
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                    <button type="button" class="ow-login__toggle" id="toggle-password" aria-controls="password" aria-label="Show password" aria-pressed="false">
                        <i class="fa fa-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <label class="ow-login__remember" for="remember">
                    <input id="remember" type="checkbox" name="remember" value="1"> Remember me on this device
                </label>

                <button type="submit" class="ow-login__submit"><i class="fa fa-sign-in" aria-hidden="true"></i> Sign in</button>
            </form>
            <p class="ow-login__note">Access is limited to authorised surveillance staff. Sessions end after a period of inactivity.</p>
        </div>
    </main>
</div>
<script src="{{ asset('js/login.js') }}"></script>
</body>
</html>
