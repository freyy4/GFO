<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Garuta Food OS</title>
    @include('_theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="admin-app">
    <a href="#main-content" class="admin-skip">Lewati navigasi</a>
    <div id="admin-shell" class="admin-shell" data-user-key="{{ auth()->id() }}">
        <button type="button" class="admin-backdrop" id="admin-backdrop" tabindex="-1" aria-label="Tutup navigasi"
            hidden></button>
        <aside id="admin-sidebar" class="admin-sidebar" aria-label="Sidebar admin" tabindex="-1">
            <div class="admin-sidebar-header">
                <a href="{{ route('dashboard') }}" class="admin-logo" aria-label="Garuta Food OS — Dashboard"><span
                        class="admin-logo-mark">GFO</span><span class="admin-nav-label">Garuta Food
                        <strong>OS</strong><small>OPERATING SYSTEM</small></span></a>
                <button type="button" class="admin-icon-button admin-mobile-close" id="admin-menu-close"
                    aria-label="Tutup navigasi">@include('_admin-icon', ['icon' => 'close'])</button>
            </div>
            <div class="admin-sidebar-scroll">@include('_navigation')</div>
            <div class="admin-sidebar-footer"><span class="admin-status-dot" aria-hidden="true"></span><span
                    class="admin-nav-label">Workspace operasional</span></div>
        </aside>
        <div class="admin-workspace">
            @include('_admin-topbar')
            <main id="main-content" tabindex="-1" class="admin-content">
                <nav aria-label="Breadcrumb" class="admin-breadcrumb">
                    <ol>
                        @foreach ($navigation['breadcrumbs'] as $crumb)
                            <li>
                                @if (!$loop->first)
                                    <span aria-hidden="true">/</span>
                                    @endif @if (!$loop->last && $crumb['url'])
                                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>@else<span
                                            aria-current="page">{{ $crumb['label'] }}</span>
                                    @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
                @include('_admin-flash')
                @hasSection('page-heading')
                    <div class="admin-page-heading">
                        <h1>@yield('page-heading')</h1>
                        <p>@yield('page-description')</p>
                    </div>
                @endif
                @yield('content')
            </main>
            <footer class="admin-footer"><span>Garuta Food OS</span><span>Operasional yang tertata, setiap hari.</span>
            </footer>
        </div>
    </div>
</body>

</html>
