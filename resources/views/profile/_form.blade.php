@csrf
@method('PATCH')
<div class="grid gap-5 sm:grid-cols-2">
@include('profile._field', ['name' => 'name', 'label' => 'Nama', 'default' => $user->name, 'autocomplete' => 'name'])
@include('profile._field', ['name' => 'phone', 'label' => 'Nomor telepon', 'default' => $user->phone, 'type' => 'tel', 'autocomplete' => 'tel', 'maxlength' => 30, 'required' => false])
@include('profile._field', ['name' => 'email', 'label' => 'Email login', 'default' => $user->email, 'type' => 'email', 'autocomplete' => 'email'])
@include('profile._field', ['name' => 'current_password', 'id' => 'profile-current-password', 'label' => 'Password saat ini', 'type' => 'password', 'autocomplete' => 'current-password'])
</div>
<p class="brand-help">Email digunakan untuk masuk. Jika email berubah, status verifikasi email akan direset. Password saat ini diperlukan untuk menyimpan perubahan.</p>
<button class="brand-primary" type="submit">Simpan profil</button>
