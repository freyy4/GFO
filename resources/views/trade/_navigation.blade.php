<nav class="flex flex-wrap gap-3 text-sm" aria-label="Navigasi Penjualan dan Inventory">
@foreach(\App\Support\TradeDefinition::LABELS as $key => $text)
@if(\App\Support\TradeDefinition::inventory($key) === \App\Support\TradeDefinition::inventory($module))
@can('trade-view', $key)<a class="brand-link" href="{{ route($key.'.index') }}" @if($key === $module) aria-current="page" @endif>{{ $text }}</a>@endcan
@endif
@endforeach
</nav>
