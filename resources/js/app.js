const themeRoot = document.documentElement;
const themeMedia = window.matchMedia('(prefers-color-scheme: dark)');
let themePreference = null;
try { themePreference = localStorage.getItem('gfo-theme'); } catch (_) {}
const validTheme = value => value === 'dark' || value === 'light';
function applyTheme() {
    const theme = validTheme(themePreference) ? themePreference : (themeMedia.matches ? 'dark' : 'light');
    themeRoot.dataset.theme = theme;
    themeRoot.style.colorScheme = theme;
    document.querySelectorAll('[data-theme-toggle]').forEach(button => {
        button.setAttribute('aria-pressed', String(theme === 'dark'));
        button.title = theme === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
    });
}
document.querySelectorAll('[data-theme-toggle]').forEach(button => button.addEventListener('click', () => {
    themePreference = themeRoot.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('gfo-theme', themePreference); } catch (_) {}
    applyTheme();
}));
themeMedia.addEventListener('change', applyTheme);
window.addEventListener('storage', event => {
    if (event.key === 'gfo-theme' || event.key === null) {
        themePreference = event.newValue;
        applyTheme();
    }
});
applyTheme();

const shell = document.getElementById('admin-shell');
if (shell) {
    const sidebar = document.getElementById('admin-sidebar');
    const workspace = shell.querySelector('.admin-workspace');
    const toggle = document.getElementById('admin-menu-toggle');
    const close = document.getElementById('admin-menu-close');
    const backdrop = document.getElementById('admin-backdrop');
    const desktop = window.matchMedia('(min-width: 1024px)');
    const storageKey = `gfo-sidebar-collapsed-${shell.dataset.userKey}`;
    let collapsed = false;
    let previousOverflow = '';
    try { collapsed = localStorage.getItem(storageKey) === 'true'; } catch (_) { /* Storage can be unavailable. */ }

    function syncToggle() {
        const expanded = desktop.matches ? !collapsed : shell.classList.contains('is-drawer-open');
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', desktop.matches ? (expanded ? 'Perkecil sidebar' : 'Perluas sidebar') : (expanded ? 'Tutup navigasi' : 'Buka navigasi'));
        sidebar.inert = !desktop.matches && !expanded;
    }
    function setCollapsed(value) {
        collapsed = value;
        shell.classList.toggle('is-collapsed', value);
        try { localStorage.setItem(storageKey, String(value)); } catch (_) { /* Continue without persistence. */ }
        syncToggle();
    }
    function closeDrawer(restoreFocus = true) {
        if (!shell.classList.contains('is-drawer-open')) return;
        shell.classList.remove('is-drawer-open');
        backdrop.hidden = true;
        workspace.inert = false;
        document.body.style.overflow = previousOverflow;
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        syncToggle();
        if (restoreFocus) toggle.focus();
    }
    function openDrawer() {
        previousOverflow = document.body.style.overflow;
        shell.classList.add('is-drawer-open');
        backdrop.hidden = false;
        workspace.inert = true;
        document.body.style.overflow = 'hidden';
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        syncToggle();
        close.focus();
    }
    toggle.addEventListener('click', () => {
        if (desktop.matches) setCollapsed(!collapsed);
        else if (shell.classList.contains('is-drawer-open')) closeDrawer();
        else openDrawer();
    });
    close.addEventListener('click', () => closeDrawer());
    backdrop.addEventListener('click', () => closeDrawer());
    sidebar.addEventListener('click', event => { if (event.target.closest('a[href]') && !desktop.matches) closeDrawer(); });
    desktop.addEventListener('change', () => { closeDrawer(false); syncToggle(); });
    setCollapsed(collapsed);

    shell.querySelectorAll('details').forEach(details => {
        const summary = details.querySelector(':scope > summary');
        const sync = () => summary?.setAttribute('aria-expanded', String(details.open));
        details.addEventListener('toggle', sync);
        sync();
    });
    shell.querySelectorAll('.admin-group-toggle').forEach(summary => {
        const positionTooltip = () => summary.style.setProperty('--tooltip-y', `${summary.getBoundingClientRect().top}px`);
        summary.addEventListener('mouseenter', positionTooltip);
        summary.addEventListener('focus', positionTooltip);
        summary.addEventListener('click', event => {
            if (desktop.matches && collapsed) {
                event.preventDefault();
                setCollapsed(false);
                summary.parentElement.open = true;
            }
        });
    });
    const popovers = [...shell.querySelectorAll('.admin-profile, .admin-unit-picker')];
    document.addEventListener('click', event => popovers.forEach(details => { if (!details.contains(event.target)) details.open = false; }));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            if (shell.classList.contains('is-drawer-open')) closeDrawer();
            popovers.forEach(details => {
                if (details.open) { details.open = false; details.querySelector('summary').focus(); }
            });
        }
        if (event.key === 'Tab' && shell.classList.contains('is-drawer-open')) {
            const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled]), summary, [tabindex="0"]')].filter(element => element.getClientRects().length);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    shell.querySelectorAll('[data-dismiss-flash]').forEach(button => button.addEventListener('click', () => {
        button.closest('[data-flash]').remove();
        document.getElementById('main-content').focus();
    }));
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!shell.contains(form)) return;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.textContent = 'Memproses…'; });
    });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
}
