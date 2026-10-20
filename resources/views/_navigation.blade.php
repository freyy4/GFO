<nav aria-label="Navigasi admin" class="admin-navigation">
    @foreach ($navigation['groups'] as $group)
        <details class="admin-group" name="admin-menu-groups" @if ($group['active']) open @endif>
            <summary class="admin-group-toggle {{ $group['active'] ? 'is-active' : '' }}" title="{{ $group['label'] }}"
                aria-expanded="{{ $group['active'] ? 'true' : 'false' }}" aria-label="{{ $group['label'] }}">
                @include('_admin-icon', ['icon' => $group['icon']])
                <span class="admin-nav-label">{{ $group['label'] }}</span>
                @if ($group['count'] !== null)
                    <span class="admin-count admin-nav-label">{{ $group['count'] }}</span>
                @endif
                @include('_admin-icon', [
                    'icon' => 'chevron',
                    'iconClass' => 'admin-chevron admin-nav-label',
                ])
                <span class="admin-tooltip" aria-hidden="true">{{ $group['label'] }}</span>
            </summary>
            <div class="admin-group-items">
                @foreach ($group['children'] as $item)
                    @include('_admin-menu-item')
                @endforeach
            </div>
        </details>
    @endforeach
</nav>
