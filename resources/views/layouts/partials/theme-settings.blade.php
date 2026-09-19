@php($brandColors = (array) config('kenfam.colors', []))
<script>
    (() => {
        const storageKey = 'laravel-aureon-dashboard-settings';
        const brand = {
            primary: @json($brandColors['primary'] ?? '#6a753d'),
            primaryDark: @json($brandColors['primary_dark'] ?? '#4e572d'),
            secondary: @json($brandColors['secondary'] ?? '#70233a'),
            accent: @json($brandColors['accent'] ?? '#b28a4b'),
        };
        const defaults = {
            mode: 'light',
            layout: 'default',
            width: 'fluid',
            palette: 'olive',
            customPrimary: brand.primary,
            sidebar: 'theme',
            sidebarBackground: 'none',
        };
        let saved = {};

        try {
            saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            const legacyTheme = localStorage.getItem('laravel-aureon-dashboard-theme');
            if (!saved.mode && ['light', 'dark'].includes(legacyTheme)) saved.mode = legacyTheme;
        } catch (_) {
            saved = {};
        }

        const settings = { ...defaults, ...saved };
        const resolvedTheme = settings.mode === 'system'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : settings.mode;
        const root = document.documentElement;

        root.dataset.bsTheme = resolvedTheme;
        root.dataset.theme = resolvedTheme;
        root.dataset.layout = settings.layout;
        root.dataset.width = settings.width;
        root.dataset.color = settings.palette;
        root.dataset.sidebar = settings.sidebar;
        root.dataset.sidebarBackground = settings.sidebarBackground;
        root.style.colorScheme = resolvedTheme;

        window.AureonDashboardTheme = { storageKey, settings, brand };
    })();
</script>
