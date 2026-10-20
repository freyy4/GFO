@php($parameters = isset($parent) ? [$parent, $record] : $record)
<div class="flex flex-wrap items-center gap-4">
@if(! isset($parent) || ! $parent->trashed())
@can('update', $record)<a class="brand-link" href="{{ route($routePrefix.'.edit', $parameters) }}">Edit</a>@endcan
@can('delete', $record)<form method="POST" action="{{ route($routePrefix.'.destroy', $parameters) }}" data-confirm="{{ $kind === 'recipe-versions' ? 'Hapus draft versi dan seluruh baris BOM? Tindakan ini permanen.' : 'Arsipkan data ini? Data yang sudah digunakan akan dilindungi.' }}">@csrf @method('DELETE')<button type="submit" class="brand-link">{{ $kind === 'recipe-versions' ? 'Hapus draft' : 'Arsipkan' }}</button></form>@endcan
@if($kind === 'recipe-versions')
@can('approve', $record)<form method="POST" action="{{ route($routePrefix.'.approve', $parameters) }}" data-confirm="Setujui versi ini? Komposisi, kuantitas, dan periode berlaku akan dikunci. Pastikan seluruh data benar.">@csrf @method('PATCH')<button type="submit" class="brand-primary">Setujui versi</button></form>@endcan
@else
@can('restore', $record)<form method="POST" action="{{ route($routePrefix.'.restore', $parameters) }}" data-confirm="Pulihkan data dalam status nonaktif?">@csrf @method('PATCH')<button type="submit" class="brand-link">Pulihkan</button></form>@endcan
@endif
@endif
</div>
