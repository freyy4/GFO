<header class="admin-topbar">
    <div class="flex min-w-0 items-center gap-3">
        <button type="button" class="admin-icon-button" id="admin-menu-toggle" aria-controls="admin-sidebar"
            aria-expanded="true" aria-label="Perkecil sidebar">@include('_admin-icon', ['icon' => 'menu'])</button>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold">@yield('title', 'Dashboard')</p>
            <p class="hidden truncate text-xs text-slate-500 sm:block">{{ $navigation['organizationName'] }}</p>
        </div>
    </div>
    <div class="flex items-center gap-2 sm:gap-4">
        @include('_theme-toggle')
        <details class="admin-unit-picker hidden md:block">
            <summary class="admin-unit-summary" aria-expanded="false">@include('_admin-icon', ['icon' => 'box'])<span
                    class="max-w-40 truncate">{{ $navigation['activeUnit']?->name ?? (request()->routeIs('dashboard', 'dashboards.*') && $navigation['units']->isNotEmpty() ? 'Semua unit' : 'Belum ada unit') }}</span>@include('_admin-icon', ['icon' => 'chevron', 'iconClass' => 'admin-chevron'])
            </summary>
            <div class="admin-popover">
                @include('_admin-unit-picker')
            </div>
        </details>
        @if ($navigation['notificationUrl'])
            <a class="admin-icon-button" href="{{ $navigation['notificationUrl'] }}"
                aria-label="Notifikasi">@include('_admin-icon', ['icon' => 'bell'])</a>
        @else
            <button type="button" class="admin-icon-button text-slate-400" aria-disabled="true"
                title="Notifikasi belum tersedia"
                aria-label="Notifikasi belum tersedia">@include('_admin-icon', ['icon' => 'bell'])</button>
        @endif
        <details class="admin-profile">
            <summary class="admin-profile-summary" aria-expanded="false"
                aria-label="Menu profil {{ $navigation['userName'] }}">
                <span class="admin-avatar">{{ $navigation['initials'] }}</span><span
                    class="hidden max-w-40 truncate text-sm font-medium sm:block">{{ $navigation['userName'] }}</span>@include('_admin-icon', ['icon' => 'chevron', 'iconClass' => 'admin-chevron'])
            </summary>
            <div class="admin-popover">
                <p class="break-words font-semibold">{{ $navigation['userName'] }}</p>
                <p class="mb-3 break-words text-xs text-slate-500">{{ $navigation['organizationName'] }}</p>
                @foreach (['Profil Saya' => $navigation['profileUrl'], 'Pengaturan Akun' => $navigation['accountUrl']] as $label => $url)
                    @if ($url)
                    <a href="{{ $url }}" class="admin-menu-link">{{ $label }}</a>@else<span
                            class="admin-menu-link is-disabled" aria-disabled="true">{{ $label }}<span
                                class="admin-soon">Segera</span></span>
                    @endif
                @endforeach
                <div class="mt-3 border-t border-slate-100 pt-3 md:hidden">@include('_admin-unit-picker')</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3 border-t border-slate-100 pt-3"
                    data-confirm="Keluar dari akun Anda?">@csrf<button type="submit"
                        class="admin-menu-link w-full text-red-700">@include('_admin-icon', ['icon' => 'logout']) Keluar</button></form>
            </div>
        </details>
    </div>
</header>
