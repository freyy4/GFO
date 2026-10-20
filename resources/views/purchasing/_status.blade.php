@php
$status = $record->getAttribute('deleted_at') ? 'ARCHIVED' : $record->getAttribute('status');
if ($module === 'supplier-items') $status = $record->is_active ? 'ACTIVE' : 'INACTIVE';
if ($module === 'supplier-price-history') {
    $today = now($timezone)->format('Y-m-d');
    $status = $record->effective_from->format('Y-m-d') > $today ? 'FUTURE' : (($record->effective_until && $record->effective_until->format('Y-m-d') < $today) ? 'EXPIRED' : 'CURRENT');
}
@endphp
<span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-green-100 text-green-800' => in_array($status, ['APPROVED','RECEIVED','CONFIRMED','ACTIVE','CURRENT']), 'bg-amber-100 text-amber-900' => in_array($status, ['DRAFT','SUBMITTED','PARTIALLY_RECEIVED','FUTURE']), 'bg-stone-100 text-stone-700' => !in_array($status, ['APPROVED','RECEIVED','CONFIRMED','ACTIVE','CURRENT','DRAFT','SUBMITTED','PARTIALLY_RECEIVED','FUTURE'])])>{{ $status }}</span>
