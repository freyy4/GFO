@php($status = $record->status ?? $record->qc_status ?? ($module === 'cash-registers' ? ($record->is_active ? 'ACTIVE' : 'INACTIVE') : 'TERCATAT'))
<span class="inline-flex rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-800">{{ $status }}</span>
