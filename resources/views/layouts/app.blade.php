@php
    $appName = config('app.name');
    $pageTitle = $title ?? 'Overview';
    $user = auth()->user();
    $initial = \Illuminate\Support\Str::of($user->name)->substr(0, 1)->upper();
    $roleLabel = $user->getRoleNames()->map(fn ($r) => str_replace('_', ' ', $r))->implode(', ') ?: 'No role';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="vendor-base" content="{{ asset('vendor/datatables') }}/">
    @if (session('status'))<meta name="flash-status" content="{{ session('status') }}">@endif
    <title>{{ $pageTitle }} · {{ $appName }}</title>

    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/AdminLTE.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/skins/skin-blue.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/datepicker/datepicker3.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/toastr/toastr.min.css') }}">
    @stack('plugin-styles')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body class="hold-transition skin-blue sidebar-mini fixed">
<a class="ow-skip-link" href="#main">Skip to main content</a>

<div class="wrapper">

    <header class="main-header">
        <a href="{{ route('dashboard') }}" class="logo">
            <span class="logo-mini"><b>OW</b></span>
            <span class="logo-lg"><i class="fa fa-heartbeat" aria-hidden="true" style="margin-right:6px"></i><b>Outbreak</b>Watch</span>
        </a>
        <nav class="navbar navbar-static-top" aria-label="Top bar">
            <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button" aria-controls="main-sidebar" aria-expanded="true">
                <span class="sr-only">Show or hide the navigation menu</span>
                <span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
            </a>
            <div class="navbar-custom-menu">
                <ul class="nav navbar-nav">
                    @stack('navbar-items')
                    <li class="dropdown user user-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            <span class="ow-avatar" aria-hidden="true">{{ $initial }}</span>
                            <span class="hidden-xs" style="margin-left:7px">{{ $user->name }}</span>
                        </a>
                        <ul class="dropdown-menu ow-user-menu">
                            <li class="ow-user-menu-head">
                                <strong>{{ $user->name }}</strong>
                                <span>{{ $user->email }}</span>
                                <span>{{ $roleLabel }}</span>
                            </li>
                            <li class="ow-user-menu-foot">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-default btn-block btn-sm">
                                        <i class="fa fa-sign-out" aria-hidden="true"></i> Sign out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
    </header>

    @include('layouts.sidebar')

    <div class="content-wrapper">
        <div class="ow-banner no-print" role="note">
            <i class="fa fa-info-circle" aria-hidden="true"></i>
            Demonstration system: weekly facility reports, immunization doses and all patient-level records are <strong>synthetic</strong>. Geography and population are open data.
        </div>
        <main id="main" tabindex="-1">
            <section class="content-header">
                <h1>{{ $pageTitle }} @hasSection('subtitle')<small>@yield('subtitle')</small>@endif</h1>
                @hasSection('actions')<div class="ow-header-actions">@yield('actions')</div>@endif
                @isset($breadcrumbs)
                <ol class="breadcrumb">
                    <li><a href="{{ route('dashboard') }}"><i class="fa fa-dashboard" aria-hidden="true"></i> Home</a></li>
                    @foreach ($breadcrumbs as $label => $url)
                        @if ($loop->last)<li class="active" aria-current="page">{{ $label }}</li>@else<li><a href="{{ $url }}">{{ $label }}</a></li>@endif
                    @endforeach
                </ol>
                @endisset
            </section>
            <section class="content">
                @yield('content')
            </section>
        </main>
    </div>

    <footer class="main-footer no-print">
        <div class="pull-right hidden-xs">Open data: HDX COD-AB, COD-PS, Healthsites, WHO GHO</div>
        <strong><i class="fa fa-heartbeat" aria-hidden="true"></i> District Outbreak Watch</strong>
        <span class="hidden-xs"> · <a href="{{ route('home') }}#data">About the data</a></span>
    </footer>
</div>

{{-- Alpine data registrations must run before Alpine starts --}}
@stack('alpine')
<script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>

<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('vendor/slimscroll/jquery.slimscroll.min.js') }}"></script>
<script src="{{ asset('vendor/fastclick/fastclick.min.js') }}"></script>
<script src="{{ asset('vendor/adminlte/js/app.min.js') }}"></script>
<script src="{{ asset('vendor/select2/select2.min.js') }}"></script>
<script src="{{ asset('vendor/datepicker/bootstrap-datepicker.js') }}"></script>
<script src="{{ asset('vendor/toastr/toastr.min.js') }}"></script>
@stack('plugins')
<script>window.owTitle = @js($pageTitle . ' · ' . $appName);</script>
<script src="{{ asset('js/ow-ui.js') }}"></script>
@stack('scripts')
</body>
</html>
