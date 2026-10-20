<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · Garuta Food OS</title>
    @include('_theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head> 
<body class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] antialiased">
    <div class="theme-guest-control">@include('_theme-toggle')</div>
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10 sm:px-6">
        <a href="{{ url('/') }}" class="mb-8 text-center text-xl font-semibold">Garuta Food <span class="text-[#f53003]">OS</span></a>
        <section class="brand-panel" aria-labelledby="login-title">
            <h1 id="login-title" class="text-2xl font-semibold">Masuk ke akun Anda</h1>
            <p class="mt-2 text-sm text-stone-600">Gunakan akun yang telah diberikan oleh administrator.</p>
            @if(session('success'))<div role="status" class="mt-5 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>@endif
            @if($errors->any())<div role="alert" class="mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"><p class="font-semibold">Login belum berhasil.</p><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5" id="login-form">
                @include('auth._form')
                <button type="submit" class="brand-primary w-full" id="login-submit">Masuk</button>
                <p class="sr-only" role="status" id="login-progress"></p>
            </form>
        </section>
        <p class="mt-6 text-center text-sm text-stone-500">Tidak dapat mengakses akun? Hubungi administrator organization Anda.</p>
    </main>
    <script>
        document.getElementById('login-form').addEventListener('submit', function (event) {
            if (this.dataset.submitting) { event.preventDefault(); return; }
            this.dataset.submitting = 'true';
            this.setAttribute('aria-busy', 'true');
            const button = document.getElementById('login-submit');
            button.disabled = true;
            button.textContent = 'Memproses…';
            document.getElementById('login-progress').textContent = 'Memeriksa akun Anda.';
        });
        window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
    </script>
</body>
</html>
