@can('update', $unit)<a class="brand-link" href="{{ route('operational-units.edit', $unit) }}" aria-label="Edit {{ $unit->name }}">Edit</a>@endcan
@can('delete', $unit)
<form method="POST" action="{{ route('operational-units.destroy', $unit) }}" data-confirm="Arsipkan unit {{ $unit->name }}? Unit akan nonaktif dan hilang dari daftar operasional. Data historis tetap tersimpan.">
    @csrf @method('DELETE')<button type="submit" class="brand-link" aria-label="Arsipkan {{ $unit->name }}">Arsipkan</button>
</form>
@endcan
