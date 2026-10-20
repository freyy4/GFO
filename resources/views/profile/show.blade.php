@extends('app')
@section('title', 'Profil Saya')
@section('page-heading', 'Profil Saya')
@section('page-description', 'Identitas akun Anda di Garuta Food OS.')
@section('content')
<section class="brand-panel space-y-6">
<div class="flex flex-wrap items-center justify-between gap-4"><h2 class="text-lg font-semibold">{{ $user->name }}</h2><span class="rounded-full bg-green-100 px-3 py-1 text-sm text-green-800">{{ $user->status }}</span></div>
<dl class="grid gap-5 sm:grid-cols-2">
@foreach(['email' => 'Email login', 'phone' => 'Nomor telepon'] as $key => $label)
<div><dt class="text-sm text-stone-500">{{ $label }}</dt><dd class="mt-1 break-words">{{ $user->$key ?: 'Belum diisi' }}</dd></div>
@endforeach
<div><dt class="text-sm text-stone-500">Organization</dt><dd>{{ $user->organization->name }}</dd></div>
<div><dt class="text-sm text-stone-500">Login terakhir (UTC)</dt><dd>{{ $user->last_login_at?->utc()->format('d M Y H:i') ?? 'Belum ada catatan login' }}</dd></div>
</dl>
<p class="brand-help">Organization, penugasan unit, role, dan status akun dikelola administrator.</p>
<a class="brand-primary inline-flex" href="{{ route('profile.edit') }}">Pengaturan Akun</a>
</section>
@endsection
