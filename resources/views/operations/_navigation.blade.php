<nav class="flex flex-wrap gap-3 text-sm" aria-label="Navigasi modul">
@foreach(\App\Support\OperationsDefinition::labels() as $key=>$text)
@if(\App\Support\OperationsDefinition::production($key) === \App\Support\OperationsDefinition::production($module))
@can('operations-view', $key)<a class="brand-link" href="{{ route($key.'.index') }}" @if($key === $module) aria-current="page" @endif>{{ $text }}</a>@endcan
@endif
@endforeach
</nav>
