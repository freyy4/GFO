<div class="flex flex-wrap items-center gap-3 text-sm">
<a class="brand-link" href="{{ route($module.'.show', $record) }}">Detail</a>
@can('update', $record)<a class="brand-link" href="{{ route($module.'.edit', $record) }}">Edit</a>@endcan
@can('delete', $record)<form method="POST" action="{{ route($module.'.destroy', $record) }}" data-confirm="{{ $module === 'positions' ? 'Hapus jabatan ini secara permanen? Jabatan yang masih digunakan tidak dapat dihapus.' : 'Arsipkan data ini? Data dapat dipulihkan kembali.' }}">@csrf @method('DELETE')<button type="submit" class="brand-link text-red-700">{{ $module === 'positions' ? 'Hapus' : 'Arsipkan' }}</button></form>@endcan
@if($module !== 'positions')@can('restore', $record)<form method="POST" action="{{ route($module.'.restore', $record) }}" data-confirm="Pulihkan data ini? Status perlu diperiksa sebelum digunakan kembali.">@csrf @method('PATCH')<button type="submit" class="brand-link">Pulihkan</button></form>@endcan
@endif
</div>
