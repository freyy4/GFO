@extends('app')
@section('title', $title)
@section('page-heading', $title)
@section('page-description', 'Ringkasan data dalam cakupan akses organization dan unit Anda.')
@section('content')
    <div class="space-y-6">
        <nav aria-label="Pilih dashboard" class="flex flex-wrap gap-3">
            <a class="brand-link" href="{{ route('dashboard') }}"
                @if ($domain === 'overview') aria-current="page" @endif>Utama</a>
            @foreach ($available as $key => $name)
                <a class="brand-link" href="{{ route('dashboards.' . $key) }}"
                    @if ($domain === $key) aria-current="page" @endif>{{ $name }}</a>
            @endforeach
        </nav>
        @if ($domain === 'overview')
            <section class="brand-panel">
                <h2 class="font-semibold">Selamat datang, {{ auth()->user()->name }}</h2>
                <p class="mt-2 text-sm text-stone-600">
                    {{ auth()->user()->organization?->name ?? 'Organization belum tersedia' }} · {{ count($available) }}
                    dashboard analitik tersedia untuk akun Anda.</p>
                @if (!$available)
                    <p class="mt-2 text-sm text-stone-600">Belum ada akses dashboard analitik. Hubungi administrator untuk
                        penempatan role dan unit.</p>
                @endif
            </section>
        @endif
        @include('dashboards._filters')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as [$cardLabel, $value, $format])
                <section class="admin-stat">
                    <p>{{ $cardLabel }}</p><strong class="break-words">
                        @if ($value === null)
                            —@else{{ $format === 'money' ? $currency . ' ' : '' }}{{ number_format((float) $value, $format === 'count' ? 0 : 2, ',', '.') }}{{ $format === 'ratio' ? '×' : '' }}
                        @endif
                    </strong><span>{{ $domain === 'overview' || str_contains($cardLabel, 'saat ini') ? 'Kondisi saat ini' : $filters['from'] . ' — ' . $filters['to'] }}</span>
                </section>
            @endforeach
        </div>
        <section class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-950"
            aria-label="Definisi ringkasan">
            <p>{{ $note }}</p>
            <p class="mt-2">Zona waktu: {{ $timezone }}. Data diperbarui saat halaman dimuat. {{ $units->count() }}
                unit tersedia dalam akses Anda.</p>
        </section>
        @if (count($distribution))
            <section class="brand-panel">
                <h2 class="mb-4 font-semibold">Distribusi status dalam hasil</h2>
                <div class="space-y-3">
                    @foreach ($distribution as $item)
                        <div class="grid grid-cols-[8rem_1fr_3rem] items-center gap-3 text-sm"><span
                                class="break-words">{{ $item->status }}</span>
                            <div class="h-3 rounded bg-stone-100" aria-hidden="true">
                                <div class="h-3 rounded bg-orange-600"
                                    style="width: {{ round(($item->total / max(1, $distribution->sum('total'))) * 100, 2) }}%">
                                </div>
                            </div><span class="text-right">{{ number_format($item->total) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        @include('dashboards._table')
    </div>
@endsection
