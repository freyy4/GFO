<span
    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $archived ?? false ? 'bg-slate-100 text-slate-700' : ($active ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800') }}">{{ $archived ?? false ? 'Diarsipkan' : ($active ? 'Aktif' : 'Nonaktif') }}</span>
