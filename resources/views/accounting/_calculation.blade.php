@if($module === 'kpi-results')
@php($detail = $record->calculation_detail ?? [])
<div class="space-y-2 border-t border-stone-200 pt-4"><h3 class="font-semibold">Dasar penilaian</h3><p>{{ $detail['formula'] ?? '—' }}</p><p>Bobot: {{ $detail['weight'] ?? '—' }}</p><p class="whitespace-pre-wrap break-words">{{ $detail['evidence'] ?? '—' }}</p></div>
@endif
@if($module === 'performance-scores')
<div class="space-y-3 border-t border-stone-200 pt-4"><h3 class="font-semibold">Rincian skor</h3><p class="brand-help">{{ $record->score_breakdown['method'] ?? '—' }}</p>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Perhitungan skor performance</caption><thead><tr><th class="p-2" scope="col">KPI</th><th class="p-2" scope="col">Skor</th><th class="p-2" scope="col">Bobot</th></tr></thead><tbody>@foreach($record->score_breakdown['results'] ?? [] as $part)<tr><th class="p-2" scope="row">{{ $part['name'] }}</th><td class="p-2">{{ $part['score'] }}</td><td class="p-2">{{ $part['weight'] }}</td></tr>@endforeach</tbody></table></div></div>
@endif
@if(in_array($module,['booth-pl','break-even']))
@php($detail = $record->calculation_detail ?? [])
<div class="space-y-3 border-t border-stone-200 pt-4"><h3 class="font-semibold">Dasar perhitungan</h3><p class="brand-help">{{ $detail['basis'] ?? 'Rincian sumber belum tersedia.' }}</p><p class="brand-help">{{ $detail['labor_assumption'] ?? '' }}</p><p>{{ $detail['break_even_note'] ?? '' }}</p>
<dl class="grid gap-4 sm:grid-cols-2">@foreach(['order_count'=>'Jumlah order','refunds_excluding_tax'=>'Refund tanpa pajak','fixed_cost'=>'Biaya tetap','variable_opex'=>'Biaya operasional variabel'] as $key=>$text)<div><dt class="text-sm text-stone-500">{{ $text }}</dt><dd>{{ $detail[$key] ?? '—' }}</dd></div>@endforeach</dl></div>
@endif
