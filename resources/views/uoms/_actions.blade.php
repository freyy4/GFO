@if ($canManage && !$uom->is_used)
    <a href="{{ route('uoms.edit', $uom) }}" class="brand-link" aria-label="Edit satuan {{ $uom->code }}">Edit</a>

    <form action="{{ route('uoms.destroy', $uom) }}" method="POST"
        data-confirm="Hapus satuan {{ $uom->code }} secara permanen dari katalog global? Tindakan ini tidak dapat dibatalkan.">
        @csrf @method('DELETE')<button type="submit" class="brand-link"
            aria-label="Hapus satuan {{ $uom->code }}">Hapus</button>
    </form>
@endif
