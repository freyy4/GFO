@if ($navigation['units']->isNotEmpty())
    @php($isDashboard = request()->routeIs('dashboard', 'dashboards.*'))
    <form method="GET" action="{{ $isDashboard ? url()->current() : route('dashboard') }}" class="space-y-3">
        @if ($isDashboard)
            @foreach (['from', 'to', 'search', 'status'] as $key)
                @if (isset($filters[$key]) && is_scalar($filters[$key]))
                    <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">
                @endif
            @endforeach
        @endif
        <label class="block text-xs font-semibold">Unit dashboard
            <select name="unit" class="brand-input mt-2" aria-label="Unit dashboard">
                <option value="">Semua unit yang diizinkan</option>
                @foreach ($navigation['units'] as $unit)
                    <option value="{{ $unit->id }}" @selected($navigation['activeUnit']?->id === $unit->id)>{{ $unit->name }}</option>
                @endforeach
            </select>
        </label>
        <p class="text-xs text-slate-500">Menampilkan data dashboard sesuai unit yang diizinkan.</p>
        <button type="submit" class="brand-primary w-full">Tampilkan</button>
    </form>
@else
    <p class="text-sm text-slate-500">Belum ada unit yang dapat diakses.</p>
@endif
