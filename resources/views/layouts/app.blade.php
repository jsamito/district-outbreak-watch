@php
    $lazyAssets = [
        'select2' => [asset_ver('vendor/select2/select2.min.css'), asset_ver('vendor/select2/select2.min.js')],
        'datepicker' => [asset_ver('vendor/datepicker/datepicker3.css'), asset_ver('vendor/datepicker/bootstrap-datepicker.js')],
        'toastr' => [asset_ver('vendor/toastr/toastr.min.css'), asset_ver('vendor/toastr/toastr.min.js')],
        'pusher' => ['', asset_ver('vendor/pusher/pusher.min.js')],
        'jquery' => ['', asset_ver('vendor/jquery/jquery.min.js')],
        'datatables' => [asset_ver('vendor/datatables/datatables.min.css'), asset_ver('vendor/datatables/datatables.min.js')],
        'tables' => ['', asset_ver('js/ow-tables.js')],
    ];
    $reverbConfig = config('broadcasting.default') === 'reverb'
        ? ['key' => config('broadcasting.connections.reverb.key'), 'host' => config('surveillance.reverb.host') ?: request()->getHost(), 'port' => config('surveillance.reverb.port'), 'scheme' => config('surveillance.reverb.scheme')]
        : null;
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
    @if ($reverbConfig && config('surveillance.realtime'))<meta name="reverb" content='@json($reverbConfig)'>@endif
    <meta name="vendor-base" content="{{ asset('vendor/datatables') }}/">
    <meta name="assets" content='@json($lazyAssets)'>
    @if (session('status'))<meta name="flash-status" content="{{ session('status') }}">@endif
    <link rel="icon" type="image/svg+xml" href="{{ asset_ver('favicon.svg') }}">
    <title>{{ $pageTitle }} · {{ $appName }}</title>

    <link rel="stylesheet" href="{{ asset_ver('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset_ver('vendor/fontawesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset_ver('vendor/adminlte/css/AdminLTE.min.css') }}">
    <link rel="stylesheet" href="{{ asset_ver('vendor/adminlte/css/skins/skin-blue.min.css') }}">
    @stack('plugin-styles')
    <link rel="stylesheet" href="{{ asset_ver('css/app.css') }}">
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
                    @if (Route::has('alerts.index') && $user->can('view alerts'))
                        @php $openAlerts = \App\Models\Alert::visibleTo($user)->where('status', 'open')->count(); @endphp
                        <li><a href="{{ route('alerts.index') }}" title="Open alerts"><i class="fa fa-bell-o" aria-hidden="true"></i>@if ($openAlerts)<span class="label label-danger" style="margin-left:3px">{{ $openAlerts }}</span>@endif<span class="sr-only">{{ $openAlerts }} open alerts</span></a></li>
                    @endif
                    @stack('navbar-items')
                    <li class="dropdown user user-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" aria-label="{{ $user->name }} account menu">
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
                                <a href="{{ route('password.edit') }}" class="btn btn-default btn-block btn-sm" style="margin-bottom:6px"><i class="fa fa-key" aria-hidden="true"></i> Change password</a>
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
        @if (config('surveillance.synthetic_data'))
        <div class="ow-banner no-print" role="note">
            <i class="fa fa-info-circle" aria-hidden="true"></i>
            <strong>Data notice:</strong> weekly facility reports, immunization doses and case records in this system are generated sample data; no real patient information is held. Geography and population come from open data (see <a href="{{ route('about') }}">About the data</a>).
        </div>
        @endif
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
        <span class="hidden-xs"> · <a href="{{ route('about') }}">About the data</a></span>
    </footer>
</div>

{{-- Alpine.js is loaded only by pages that declare it (it needs 'unsafe-eval', so the strict policy leaves it out by default) --}}
@stack('alpine')
@stack('alpine-lib')

@stack('plugins')
<script @nonce>window.owTitle = @js($pageTitle . ' · ' . $appName);</script>
<script src="{{ asset_ver('js/ow-ui.js') }}"></script>
@stack('scripts')
</body>
</html>
