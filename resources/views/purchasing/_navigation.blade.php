<nav aria-label="Purchasing" class="flex flex-wrap gap-4 text-sm">
@foreach(\App\Support\PurchasingDefinition::LABELS as $key => $caption)
@can('purchasing-view', $key)<a class="brand-link" href="{{ route($key.'.index') }}" @if($module === $key) aria-current="page" @endif>{{ $caption }}</a>@endcan
@endforeach
</nav>
