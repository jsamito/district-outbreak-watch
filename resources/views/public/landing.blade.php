<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <meta name="description" content="Disease surveillance dashboard for district and national health teams in Uganda, using open and synthetic data.">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="ow-public">
<a class="ow-skip-link" href="#main">Skip to main content</a>
<header class="ow-public__header">
    <strong>Outbreak</strong>Watch
</header>
<main id="main" class="container" tabindex="-1">
    <h1>District Outbreak Watch</h1>
    <p class="lead">A secure surveillance dashboard for district and national health teams.</p>
    @auth
        <p><a class="btn btn-primary btn-lg" href="{{ route('dashboard') }}">Go to the dashboard</a></p>
    @else
        <p><a class="btn btn-primary btn-lg" href="{{ route('login') }}">Sign in</a></p>
    @endauth
</main>
</body>
</html>
