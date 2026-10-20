<span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
    'bg-green-100 text-green-800' => $brand->status === 'ACTIVE',
    'bg-amber-100 text-amber-800' => $brand->status === 'DRAFT',
    'bg-stone-100 text-stone-700' => $brand->status === 'INACTIVE',
    'bg-red-100 text-red-800' => $brand->status === 'ARCHIVED'])>{{ $brand->status }}</span>
@if($brand->trashed())<span class="ml-1 text-xs text-stone-500">Terhapus</span>@endif
