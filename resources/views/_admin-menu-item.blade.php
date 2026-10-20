@if ($item['children'])
    <details class="admin-subgroup" @if ($item['active']) open @endif>
        <summary class="admin-menu-link {{ $item['active'] ? 'is-active' : '' }}"
            aria-expanded="{{ $item['active'] ? 'true' : 'false' }}">
            <span>{{ $item['label'] }}</span>@include('_admin-icon', ['icon' => 'chevron', 'iconClass' => 'admin-chevron'])
        </summary>
        <div class="admin-submenu">
            @if ($item['enabled'])
                <a class="admin-menu-link" href="{{ $item['url'] }}"
                    @if ($item['active'] && !collect($item['children'])->contains('active', true)) aria-current="page" @endif>Semua {{ $item['label'] }}</a>
            @else<span class="admin-menu-link is-disabled" aria-disabled="true">Semua {{ $item['label'] }} <span
                        class="admin-soon">{{ $item['disabledLabel'] }}</span></span>
            @endif
            @foreach ($item['children'] as $child)
                @include('_admin-menu-item', ['item' => $child])
            @endforeach
        </div>
    </details>
@else
    @if ($item['enabled'])
        <a class="admin-menu-link {{ $item['active'] ? 'is-active' : '' }} {{ $item['prominent'] ?? false ? 'admin-pos' : '' }}"
            href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif>
            <span>{{ $item['label'] }}</span>
            @if ($item['count'] !== null)
                <span class="admin-count">{{ $item['count'] }}</span>
            @endif
        </a>
    @else
        <span class="admin-menu-link is-disabled {{ $item['prominent'] ?? false ? 'admin-pos' : '' }}"
            aria-disabled="true"><span>{{ $item['label'] }}</span><span
                class="admin-soon">{{ $item['disabledLabel'] }}</span></span>
    @endif
@endif
