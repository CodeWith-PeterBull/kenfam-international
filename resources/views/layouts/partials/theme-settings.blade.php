<script>
    (() => {
        const storageKey = 'laravel-aureon-dashboard-settings';
        const defaults = {
            mode: 'light',
            layout: 'default',
            width: 'fluid',
            palette: 'wine',
            customPrimary: '#70233a',
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

        window.AureonDashboardTheme = { storageKey, settings };
    })();
</script>
