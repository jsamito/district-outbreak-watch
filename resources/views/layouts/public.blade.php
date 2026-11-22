<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset_ver('favicon.svg') }}">
    <title>@yield('title', config('app.name')) · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Disease surveillance for district and national health teams in Uganda: weekly counts, alerts and projections for notifiable diseases.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', config('app.name'))">
    <meta property="og:description" content="@yield('description', 'National weekly disease situation and data sources.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ route('share.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="stylesheet" href="{{ asset_ver('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset_ver('vendor/fontawesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset_ver('css/app.css') }}">
    <script type="application/ld+json" @nonce>{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => config('app.name'), 'url' => url('/'), 'inLanguage' => 'en'], JSON_UNESCAPED_SLASHES) !!}</script>
    @stack('head')
</head>
<body class="ow-public">
<a class="ow-skip-link" href="#main">Skip to main content</a>
<header class="ow-public__header">
    <div class="container ow-public__bar">
        <a href="{{ route('home') }}" class="ow-public__brand"><i class="fa fa-heartbeat" aria-hidden="true"></i> <strong>Outbreak</strong>Watch</a>
        <nav aria-label="Public pages">
            <ul class="ow-public__nav">
                <li><a href="{{ route('situation') }}" @if (request()->routeIs('situation')) aria-current="page" @endif>National situation</a></li>
                <li><a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>About the data</a></li>
                <li>@auth<a class="btn btn-primary btn-sm" href="{{ route('dashboard') }}">Dashboard</a>@else<a class="btn btn-primary btn-sm" href="{{ route('login') }}">Sign in</a>@endauth</li>
            </ul>
        </nav>
    </div>
</header>
<main id="main" class="container" tabindex="-1">
    @yield('content')
</main>
<footer class="ow-public__footer">
    <div class="container">
        <a href="{{ route('accessibility') }}">Accessibility</a> · <a href="{{ route('privacy') }}">Privacy</a> · <a href="{{ route('about') }}">Data sources and licences</a>
        <p class="text-muted" style="margin:8px 0 0">Public pages show national totals only. District, facility and case information is available to signed-in staff.</p>
    </div>
</footer>
</body>
</html>
