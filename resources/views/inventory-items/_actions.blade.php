@if($item->trashed())
    @if($abilities['delete'])<form method="POST" action="{{ route('inventory-items.restore', $item) }}" data-confirm="Pulihkan {{ $item->name }} dalam status nonaktif?">@csrf @method('PATCH')<button type="submit" class="brand-link" aria-label="Pulihkan {{ $item->sku }}">Pulihkan</button></form>@endif
@else
    @if($abilities['update'])<a class="brand-link" href="{{ route('inventory-items.edit', $item) }}" aria-label="Edit {{ $item->sku }}">Edit</a>@endif
    @if($abilities['delete'])<form method="POST" action="{{ route('inventory-items.destroy', $item) }}" data-confirm="Arsipkan {{ $item->name }}? Item akan dinonaktifkan. Item yang sudah digunakan tidak dapat diarsipkan.">@csrf @method('DELETE')<button type="submit" class="brand-link" aria-label="Arsipkan {{ $item->sku }}">Arsipkan</button></form>@endif
@endif
