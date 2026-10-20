@extends('app')
@section('title', 'Detail satuan')
@section('page-heading', $uom->code . ' · ' . $uom->name)
@section('page-description', 'Definisi satuan dalam katalog global.')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">@include('uoms._status')<div
            class="flex items-center gap-4">@include('uoms._actions')</div>
    </div>
    <section class="brand-panel">
        <h2 class="mb-5 text-lg font-semibold">Informasi satuan</h2>
        <dl class="grid gap-6 sm:grid-cols-2">
            @foreach (['Kode' => $uom->code, 'Nama' => $uom->name, 'Dimensi' => $uom->dimension, 'Jumlah desimal' => $uom->decimal_places, 'Cakupan' => 'Global — semua organization dan unit', 'Diperbarui pada' => $uom->updated_at?->format('d M Y H:i')] as $label => $value)
                <div>
                    <dt class="brand-label">{{ $label }}</dt>
                    <dd class="break-words">{{ $value ?? '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
    @if ($uom->is_used)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">Satuan ini telah
            digunakan pada konversi atau data operasional. Definisi tidak dapat diubah atau dihapus. Rincian transaksi
            organization lain tidak ditampilkan.</div>
    @endif
    <a href="{{ route('uoms.index') }}" class="brand-link">Kembali ke daftar satuan</a>
@endsection
