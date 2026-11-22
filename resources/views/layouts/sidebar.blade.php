@php
    $navItems = \App\Support\Navigation::for(auth()->user());
    $u = auth()->user();
@endphp
<aside class="main-sidebar" id="main-sidebar" aria-label="Sidebar">
    <section class="sidebar">
        <div class="user-panel">
            <div class="pull-left image">
                <span class="ow-avatar" style="width:36px;height:36px;font-size:15px" aria-hidden="true">{{ \Illuminate\Support\Str::of($u->name)->substr(0, 1)->upper() }}</span>
            </div>
            <div class="pull-left info" style="padding:0 0 0 10px">
                <p>{{ $u->name }}</p>
                <small>{{ $u->getRoleNames()->map(fn ($r) => str_replace('_', ' ', $r))->implode(', ') ?: 'No role' }}</small>
            </div>
        </div>

        <nav aria-label="Main navigation">
            <ul class="sidebar-menu">
                @foreach ($navItems as $item)
                    @if (isset($item['heading']))
                        <li class="header">{{ strtoupper($item['heading']) }}</li>
                    @else
                        @php
                            $active = request()->routeIs($item['route'], ...($item['also'] ?? []));
                            $badge = isset($item['badge']) ? ($item['badge'])($u) : null;
                        @endphp
                        <li @class(['active' => $active])>
                            <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif>
                                <i class="fa {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                                @if ($badge)<span class="sidebar-badge {{ $badge['class'] ?? '' }}">{{ $badge['count'] }}<span class="sr-only"> {{ $badge['label'] ?? '' }}</span></span>@endif
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </section>
</aside>
