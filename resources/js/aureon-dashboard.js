const root = document.documentElement;
// The olive palette is the server-side brand set (config kenfam.colors) handed over by the theme-settings partial.
const brand = window.AureonDashboardTheme?.brand || {};
const defaults = {
    mode: 'light',
    layout: 'default',
    width: 'fluid',
    palette: 'olive',
    customPrimary: brand.primary || '#6a753d',
    sidebar: 'theme',
    sidebarBackground: 'none',
};
const palettes = {
    olive: { primary: brand.primary || '#6a753d', primaryDark: brand.primaryDark || '#4e572d', secondary: brand.secondary || '#70233a', accent: brand.accent || '#b28a4b' },
    wine: { primary: '#70233a', primaryDark: '#54182b', secondary: '#28656b', accent: '#b28a4b' },
    teal: { primary: '#28656b', primaryDark: '#1d4b50', secondary: '#70233a', accent: '#b28a4b' },
    gold: { primary: '#8a6427', primaryDark: '#684917', secondary: '#28656b', accent: '#70233a' },
    graphite: { primary: '#40434a', primaryDark: '#292b30', secondary: '#28656b', accent: '#b28a4b' },
};
const allowed = {
    mode: ['light', 'dark', 'system'],
    layout: ['default', 'mini', 'detached'],
    width: ['fluid', 'box'],
    palette: [...Object.keys(palettes), 'custom'],
    sidebar: ['theme', 'light', 'dark', 'brand'],
    sidebarBackground: ['none', ...Array.from({ length: 6 }, (_, index) => `sidebarbg${index + 1}`)],
};
const bridge = window.AureonDashboardTheme || {};
const storageKey = bridge.storageKey || 'laravel-aureon-dashboard-settings';
const state = normalizeSettings(bridge.settings || {});
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');

function normalizeSettings(settings) {
    const normalized = { ...defaults, ...settings };

    Object.entries(allowed).forEach(([key, values]) => {
        if (!values.includes(normalized[key])) normalized[key] = defaults[key];
    });

    if (!/^#[0-9a-f]{6}$/i.test(normalized.customPrimary)) {
        normalized.customPrimary = defaults.customPrimary;
    }

    return normalized;
}

function resolvedMode() {
    if (state.mode !== 'system') return state.mode;
    return systemTheme.matches ? 'dark' : 'light';
}

function shadeHex(hex, factor) {
    const channels = hex.slice(1).match(/.{2}/g).map((channel) => parseInt(channel, 16));
    return `#${channels.map((channel) => Math.max(0, Math.min(255, Math.round(channel * factor))).toString(16).padStart(2, '0')).join('')}`;
}

function rgbValue(hex) {
    return hex.slice(1).match(/.{2}/g).map((channel) => parseInt(channel, 16)).join(', ');
}

function activePalette() {
    if (state.palette !== 'custom') return palettes[state.palette];

    return {
        primary: state.customPrimary,
        primaryDark: shadeHex(state.customPrimary, 0.74),
        secondary: '#28656b',
        accent: '#b28a4b',
    };
}

function persistSettings() {
    try {
        localStorage.setItem(storageKey, JSON.stringify(state));
        localStorage.setItem('laravel-aureon-dashboard-theme', resolvedMode());
    } catch (_) {
        // The dashboard still works when storage is unavailable.
    }
}

function syncControls() {
    document.querySelectorAll('[data-dashboard-setting]').forEach((input) => {
        input.checked = state[input.dataset.dashboardSetting] === input.value;
    });

    const customInput = document.querySelector('[data-theme-custom-color]');
    if (customInput) customInput.value = state.customPrimary;
    customInput?.closest('.aureon-palette--custom')?.classList.toggle('is-active', state.palette === 'custom');

    const isDark = resolvedMode() === 'dark';
    document.querySelectorAll('[data-dashboard-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(isDark));
        button.setAttribute('title', isDark ? 'Use light theme' : 'Use dark theme');
        const icon = button.querySelector('i');
        if (icon) icon.className = isDark ? 'ti ti-sun' : 'ti ti-moon';
    });
}

function applySettings({ persist = true, announce = false } = {}) {
    const mode = resolvedMode();
    const palette = activePalette();

    root.dataset.bsTheme = mode;
    root.dataset.theme = mode;
    root.dataset.layout = state.layout;
    root.dataset.width = state.width;
    root.dataset.color = state.palette;
    root.dataset.sidebar = state.sidebar;
    root.dataset.sidebarBackground = state.sidebarBackground;
    root.style.colorScheme = mode;
    root.style.setProperty('--aureon-primary', palette.primary);
    root.style.setProperty('--aureon-primary-dark', palette.primaryDark);
    root.style.setProperty('--aureon-primary-rgb', rgbValue(palette.primary));
    root.style.setProperty('--aureon-secondary', palette.secondary);
    root.style.setProperty('--aureon-accent', palette.accent);
    root.style.setProperty('--bs-primary-rgb', rgbValue(palette.primary));

    document.body.classList.toggle('mini-sidebar', state.layout === 'mini');
    document.body.classList.toggle('layout-box-mode', state.width === 'box');
    if (state.layout !== 'mini') document.body.classList.remove('expand-menu');

    if (state.sidebarBackground === 'none') document.body.removeAttribute('data-sidebarbg');
    else document.body.dataset.sidebarbg = state.sidebarBackground;

    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', palette.primary);
    syncControls();
    if (persist) persistSettings();

    if (announce) {
        const status = document.querySelector('[data-dashboard-settings-status]');
        if (status) status.textContent = 'Dashboard settings updated';
    }

    window.dispatchEvent(new CustomEvent('aureon:dashboard-settings', { detail: { ...state, resolvedMode: mode } }));
}

applySettings({ persist: false });

document.addEventListener('DOMContentLoaded', () => {
    applySettings({ persist: false });

    document.querySelectorAll('[data-dashboard-setting]').forEach((input) => {
        input.addEventListener('change', () => {
            state[input.dataset.dashboardSetting] = input.value;
            applySettings({ announce: true });
        });
    });

    document.querySelector('[data-theme-custom-color]')?.addEventListener('input', (event) => {
        state.customPrimary = event.target.value;
        state.palette = 'custom';
        applySettings({ announce: true });
    });

    document.querySelectorAll('[data-dashboard-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            state.mode = resolvedMode() === 'dark' ? 'light' : 'dark';
            applySettings({ announce: true });
        });
    });

    document.querySelector('[data-dashboard-settings-reset]')?.addEventListener('click', () => {
        Object.assign(state, defaults);
        applySettings({ announce: true });
    });

    document.querySelector('#toggle_btn')?.addEventListener('click', () => {
        window.setTimeout(() => {
            state.layout = document.body.classList.contains('mini-sidebar') ? 'mini' : 'default';
            root.dataset.layout = state.layout;
            persistSettings();
            syncControls();
        });
    });

    document.querySelectorAll('[data-current-year]').forEach((node) => {
        node.textContent = String(new Date().getFullYear());
    });

});

systemTheme.addEventListener('change', () => {
    if (state.mode === 'system') applySettings({ persist: false });
});

window.addEventListener('storage', (event) => {
    if (event.key !== storageKey || !event.newValue) return;

    try {
        Object.assign(state, normalizeSettings(JSON.parse(event.newValue)));
        applySettings({ persist: false });
    } catch (_) {
        // Ignore malformed settings from another tab.
    }
});
