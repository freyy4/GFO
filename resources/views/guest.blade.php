<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Garuta Food') · Garuta Food OS</title>
    @include('_theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#FDFDFC] text-[#1b1b18] antialiased">
    <div class="theme-guest-control">@include('_theme-toggle')</div>
    <main id="main-content" class="mx-auto w-full max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-800">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="brand-panel text-red-700">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
                <p class="font-semibold">Periksa kembali data berikut:</p>
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
    <script>
        document.addEventListener('submit', function(event) {
            const form = event.target;
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                event.preventDefault();
                return;
            }
            if (form.dataset.submitting) {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            const progress = form.querySelector('[role="status"]');
            if (progress) progress.textContent = 'Memproses…';
            form.querySelectorAll('button[type="submit"]').forEach(button => {
                button.disabled = true;
                button.textContent = 'Memproses…';
            });
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted) window.location.reload();
        });
    </script>
</body>

</html>
