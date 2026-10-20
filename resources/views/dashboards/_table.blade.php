<section class="overflow-hidden rounded-xl border border-stone-200 bg-white">
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 p-5"><h2 class="font-semibold">{{ $tableTitle }}</h2><span class="text-sm text-stone-500">{{ number_format($rows->total()) }} data</span></div>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">{{ $tableTitle }}</caption>
<thead class="bg-stone-50"><tr>
@php($columns = match($domain) {'overview' => ['Kode', 'Unit', 'Jenis', 'Status'], 'operations' => ['Nomor pesanan', 'Waktu', 'Unit', 'Total ('.$currency.')', 'Status'], 'hr' => ['Karyawan', 'Tanggal kerja', 'Unit', 'Terlambat (menit)', 'Status'], default => ['Campaign', 'Periode campaign', 'Spend ('.$currency.')', 'Revenue ('.$currency.')', 'Status']})
@foreach($columns as $column)<th scope="col" class="whitespace-nowrap px-4 py-3 font-medium">{{ $column }}</th>@endforeach
</tr></thead><tbody class="divide-y divide-stone-100">
@forelse($rows as $row)<tr>
@if($domain === 'overview')
<td class="px-4 py-4 font-mono">{{ $row->code }}</td><td class="px-4 py-4">{{ $row->name }}</td><td class="px-4 py-4">{{ $row->type }}</td>
@elseif($domain === 'operations')
<td class="px-4 py-4 font-mono">{{ $row->number }}</td><td class="whitespace-nowrap px-4 py-4">{{ $row->ordered_at->timezone($timezone)->format('d M Y H:i') }}</td><td class="px-4 py-4">{{ $row->operationalUnit?->name ?? '—' }}</td><td class="whitespace-nowrap px-4 py-4">{{ number_format((float)$row->total_amount, 2, ',', '.') }}</td>
@elseif($domain === 'hr')
<td class="px-4 py-4">{{ $row->employee?->full_name ?? '—' }}<span class="block text-xs text-stone-500">{{ $row->employee?->employee_no }}</span></td><td class="whitespace-nowrap px-4 py-4">{{ $row->business_date->format('d M Y') }}</td><td class="px-4 py-4">{{ $row->operationalUnit?->name ?? '—' }}</td><td class="px-4 py-4">{{ number_format($row->late_minutes) }}</td>
@else
<td class="px-4 py-4">{{ $row->name }}<span class="block font-mono text-xs text-stone-500">{{ $row->code }}</span></td><td class="whitespace-nowrap px-4 py-4">{{ $row->starts_at->timezone($timezone)->format('d M Y') }} — {{ $row->ends_at->timezone($timezone)->format('d M Y') }}</td><td class="px-4 py-4">{{ $row->period_spend !== null ? number_format((float)$row->period_spend, 2, ',', '.') : '—' }}</td><td class="px-4 py-4">{{ $row->period_revenue !== null ? number_format((float)$row->period_revenue, 2, ',', '.') : '—' }}</td>
@endif
<td class="px-4 py-4"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-green-100 text-green-800' => in_array($row->status, ['ACTIVE', 'COMPLETED', 'APPROVED']), 'bg-stone-100 text-stone-700' => !in_array($row->status, ['ACTIVE', 'COMPLETED', 'APPROVED'])])>{{ $row->status }}</span></td>
</tr>@empty<tr><td colspan="{{ count($columns) }}" class="px-6 py-14 text-center"><h3 class="font-semibold">Belum ada data sesuai filter</h3><p class="mt-2 text-stone-500">Ubah periode, pencarian, atau unit. Hanya data dalam hak akses Anda yang ditampilkan.</p></td></tr>@endforelse
</tbody></table></div>
<div class="border-t border-stone-100 p-4">{{ $rows->links() }}</div>
</section>
