@extends('app')
@section('title', 'Pengaturan Akun')
@section('page-heading', 'Pengaturan Akun')
@section('page-description', 'Perbarui identitas dan password akun Anda.')
@section('content')
<div class="space-y-6">
<form class="brand-panel space-y-5" method="POST" action="{{ route('profile.update') }}" data-confirm="Simpan perubahan profil dan email login Anda?">
<h2 class="text-lg font-semibold">Informasi profil</h2>
@include('profile._form')
</form>
<form class="brand-panel space-y-5" method="POST" action="{{ route('profile.password') }}" data-confirm="Ubah password? Anda akan keluar dan perlu masuk kembali.">
@csrf
@method('PUT')
<h2 class="text-lg font-semibold">Ubah password</h2>
<p class="brand-help">Gunakan minimal 12 karakter dengan huruf besar, huruf kecil, angka, dan simbol; maksimal 72 byte. Setelah berhasil, Anda akan diminta masuk kembali.</p>
<div class="grid gap-5 sm:grid-cols-2">
@include('profile._field', ['name' => 'current_password', 'id' => 'security-current-password', 'label' => 'Password saat ini', 'type' => 'password', 'autocomplete' => 'current-password'])
@include('profile._field', ['name' => 'password', 'label' => 'Password baru', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 72])
@include('profile._field', ['name' => 'password_confirmation', 'label' => 'Ulangi password baru', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 72])
</div>
<button class="brand-primary" type="submit">Ubah password</button>
</form>
<a class="brand-link" href="{{ route('profile.show') }}">Kembali ke profil</a>
</div>
@endsection
