@extends('app')
@section('title', 'Satuan/UOM')
@section('page-heading', 'Satuan / Unit of Measure')
@section('page-description', 'Kelola definisi satuan yang konsisten untuk kebutuhan operasional.')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="max-w-2xl text-sm text-slate-500">Katalog global untuk semua organization dan unit. Satuan yang sudah
            digunakan dilindungi agar data historis tetap konsisten.</p>
        @can('create', App\Models\UnitOfMeasure::class)
            <a href="{{ route('uoms.create') }}" class="brand-primary">Tambah satuan</a>
        @endcan
    </div>
    <form method="GET" action="{{ route('uoms.index') }}" class="brand-panel grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        role="search">
        <div><label for="search" class="brand-label">Cari satuan</label><input type="search" id="search" name="search"
                class="brand-input" maxlength="100" value="{{ $filters['search'] ?? '' }}"
                placeholder="Kode atau nama satuan"></div>
        <div><label for="dimension" class="brand-label">Dimensi</label><select id="dimension" name="dimension"
                class="brand-input">
                <option value="">Semua dimensi</option>
                @foreach ($dimensions as $dimension)
                    <option value="{{ $dimension }}" @selected(($filters['dimension'] ?? '') === $dimension)>{{ $dimension }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="usage" class="brand-label">Status penggunaan</label><select id="usage" name="usage"
                class="brand-input">
                <option value="">Semua status</option>
                <option value="used" @selected(($filters['usage'] ?? '') === 'used')>Terpakai / dilindungi</option>
                <option value="unused" @selected(($filters['usage'] ?? '') === 'unused')>Belum terpakai</option>
            </select></div>
        <div class="flex items-end gap-4"><button type="submit" class="brand-primary">Terapkan</button><a
                href="{{ route('uoms.index') }}" class="brand-link">Reset</a></div>
    </form>
    <section class="brand-panel">
        <p class="mb-4 text-sm text-slate-500" role="status">{{ $uoms->total() }} satuan ditemukan</p>
        @if ($uoms->isEmpty())
            <div class="admin-empty">@include('_admin-icon', ['icon' => 'grid'])<h2 class="font-semibold">Belum ada satuan yang cocok</h2>
                <p>Coba ubah pencarian atau filter. SUPER_ADMIN dapat menambahkan satuan baru.</p><a
                    href="{{ route('uoms.index') }}" class="brand-link">Reset pencarian</a>
            </div>
        @else
            <div class="overflow-x-auto" role="region" aria-label="Daftar satuan" tabindex="0">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Katalog satuan global</caption>
                    <thead>
                        <tr class="border-b border-slate-200">
                            @foreach (['Kode', 'Nama', 'Dimensi', 'Desimal', 'Status', 'Aksi'] as $label)
                                <th scope="col" class="px-3 py-3">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($uoms as $uom)
                            <tr class="border-b border-slate-100">
                                <th scope="row" class="px-3 py-4"><a class="brand-link"
                                        href="{{ route('uoms.show', $uom) }}">{{ $uom->code }}</a></th>
                                <td class="px-3 py-4">{{ $uom->name }}</td>
                                <td class="px-3 py-4">{{ $uom->dimension ?? '—' }}</td>
                                <td class="px-3 py-4">{{ $uom->decimal_places }}</td>
                                <td class="px-3 py-4">@include('uoms._status')</td>
                                <td class="px-3 py-4">
                                    <div class="flex flex-wrap items-center gap-3"><a class="brand-link"
                                            href="{{ route('uoms.show', $uom) }}"
                                            aria-label="Detail satuan {{ $uom->code }}">Detail</a>@include('uoms._actions')
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $uoms->links() }}</div>
        @endif
    </section>
@endsection
