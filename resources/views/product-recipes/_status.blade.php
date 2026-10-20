@if($kind === 'recipe-versions')
<span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $record->status === 'APPROVED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">{{ $record->status === 'APPROVED' ? 'Approved · Dikunci' : 'Draft' }}</span>
@else
@include('_catalog-status', ['active' => $kind === 'recipes' ? $record->status === 'ACTIVE' : $record->is_active, 'archived' => $record->trashed()])
@endif
