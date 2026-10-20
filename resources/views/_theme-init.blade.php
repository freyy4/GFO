<script>
    // Apply before CSS loads to avoid flashing the opposite theme.
    (() => {
        let saved;
        try { saved = localStorage.getItem('gfo-theme'); } catch (_) {}
        const dark = saved === 'dark' || (saved !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    })();
</script>
