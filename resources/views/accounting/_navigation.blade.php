<nav class="flex flex-wrap gap-3 text-sm" aria-label="Navigasi modul">
@foreach(\App\Support\AccountingDefinition::LABELS as $key=>$text)
@if(\App\Support\AccountingDefinition::payroll($key) === \App\Support\AccountingDefinition::payroll($module))
@can('accounting-view', $key)<a class="brand-link" href="{{ route($key.'.index') }}" @if($key === $module) aria-current="page" @endif>{{ $text }}</a>@endcan
@endif
@endforeach
</nav>
