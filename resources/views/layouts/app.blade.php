@php
    $navItems = \App\Support\Navigation::for(auth()->user());
    $pageTitle = $title ?? 'Overview';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $pageTitle }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/AdminLTE.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/skins/skin-blue.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body class="hold-transition skin-blue sidebar-mini">
<a class="ow-skip-link" href="#main">Skip to main content</a>
<div class="wrapper">

    <header class="main-header">
        <a href="{{ route('dashboard') }}" class="logo">
            <span class="logo-mini"><strong>OW</strong></span>
            <span class="logo-lg"><strong>Outbreak</strong>Watch</span>
        </a>
        <nav class="navbar navbar-static-top" aria-label="Top bar">
            <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button" aria-controls="main-sidebar" aria-label="Show or hide the navigation menu"></a>
            <div class="navbar-custom-menu">
                <ul class="nav navbar-nav">
                    <li class="dropdown user user-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            <i class="fa fa-user-circle" aria-hidden="true"></i>
                            <span class="hidden-xs">{{ auth()->user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu ow-user-menu">
                            <li class="ow-user-menu__info">
                                <strong>{{ auth()->user()->name }}</strong>
                                <span>{{ auth()->user()->job_title }}</span>
                                <span>{{ auth()->user()->getRoleNames()->map(fn ($r) => str_replace('_', ' ', $r))->join(', ') }}</span>
                            </li>
                            <li class="ow-user-menu__action">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-default btn-block">Sign out</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
    </header>

    <aside class="main-sidebar" id="main-sidebar" aria-label="Sidebar">
        <section class="sidebar">
            <nav aria-label="Main navigation">
                <ul class="sidebar-menu">
                    @foreach ($navItems as $item)
                        @if (isset($item['heading']))
                            <li class="header">{{ $item['heading'] }}</li>
                        @else
                            @php $active = request()->routeIs($item['route']) || request()->routeIs(\Illuminate\Support\Str::before($item['route'], '.').'.*'); @endphp
                            <li @class(['active' => $active])>
                                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif>
                                    <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </nav>
        </section>
    </aside>

    <div class="content-wrapper">
        <div class="ow-banner" role="note">
            <i class="fa fa-info-circle" aria-hidden="true"></i>
            Demonstration system. Patient-level and weekly facility data are <strong>synthetic</strong>; geography and population are open data.
        </div>

        <div class="content-header">
            <h1>{{ $pageTitle }} @isset($subtitle)<small>{{ $subtitle }}</small>@endisset</h1>
        </div>

        <main id="main" class="content" tabindex="-1">
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>

    <footer class="main-footer">
        <span>District Outbreak Watch</span>
        <span class="hidden-xs"> · Data sources and licences: see <a href="{{ route('home') }}#data">About the data</a></span>
    </footer>
</div>

<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('vendor/adminlte/js/app.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
<script defer src="{{ asset('vendor/alpine/alpine.csp.min.js') }}"></script>
</body>
</html>
