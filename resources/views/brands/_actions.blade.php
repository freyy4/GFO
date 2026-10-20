<div class="flex flex-wrap items-center gap-3 text-sm">
    <a class="brand-link" href="{{ route('brands.show', $brand) }}">Detail</a>
    @can('update', $brand)<a class="brand-link" href="{{ route('brands.edit', $brand) }}">Edit</a>@endcan
    @can('activate', $brand)<form method="POST" action="{{ route('brands.activate', $brand) }}">@csrf @method('PATCH')<button type="submit" class="brand-link">Aktifkan</button></form>@endcan
    @can('deactivate', $brand)<form method="POST" action="{{ route('brands.deactivate', $brand) }}" data-confirm="Nonaktifkan brand {{ $brand->name }}?">@csrf @method('PATCH')<button type="submit" class="brand-link">Nonaktifkan</button></form>@endcan
    @can('delete', $brand)<form method="POST" action="{{ route('brands.destroy', $brand) }}" data-confirm="Hapus brand {{ $brand->name }}? Brand akan diarsipkan dan dapat dipulihkan.">@csrf @method('DELETE')<button type="submit" class="text-red-700 underline underline-offset-4">Hapus</button></form>@endcan
    @can('restore', $brand)<form method="POST" action="{{ route('brands.restore', $brand) }}">@csrf @method('PATCH')<button type="submit" class="brand-link">Pulihkan</button></form>@endcan
</div>
