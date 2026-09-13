function initializePropertyBookingAdmin() {
    document.querySelectorAll('[data-property-booking-admin] [data-bs-toggle="tooltip"]').forEach((element) => {
        if (window.bootstrap?.Tooltip && !window.bootstrap.Tooltip.getInstance(element)) {
            window.bootstrap.Tooltip.getOrCreateInstance(element);
        }
    });
}

let propertyBookingChart = null;

/** Render the optional operations trend using the dashboard-provided Chart runtime. */
function initializePropertyBookingDashboard() {
    const canvas = document.querySelector('[data-property-booking-trend-chart]');
    const payloadNode = document.querySelector('#property-booking-dashboard-chart-data');
    if (!canvas || !payloadNode || typeof window.Chart !== 'function') return;

    let payload;
    try {
        payload = JSON.parse(payloadNode.textContent || '{}');
    } catch (_) {
        return;
    }

    const styles = getComputedStyle(document.documentElement);
    const colors = {
        primary: styles.getPropertyValue('--aureon-primary').trim() || '#70233a',
        secondary: styles.getPropertyValue('--aureon-secondary').trim() || '#28656b',
        tertiary: '#8a6427',
        border: styles.getPropertyValue('--aureon-border').trim() || '#e2e4e8',
        ink: styles.getPropertyValue('--aureon-ink').trim() || '#1d1d25',
        muted: styles.getPropertyValue('--aureon-muted').trim() || '#6d7079',
    };
    const divisor = 10 ** Number(payload.decimals || 0);
    const money = new Intl.NumberFormat(document.documentElement.lang || 'en', {
        style: 'currency',
        currency: payload.currency || 'KES',
        maximumFractionDigits: Number(payload.decimals || 0),
    });
    const compactMoney = new Intl.NumberFormat(document.documentElement.lang || 'en', {
        style: 'currency',
        currency: payload.currency || 'KES',
        notation: 'compact',
        maximumFractionDigits: 1,
    });

    propertyBookingChart?.destroy();
    propertyBookingChart = new window.Chart(canvas, {
        type: 'line',
        data: {
            labels: payload.labels || [],
            datasets: [
                { label: 'Web', data: payload.web || [], borderColor: colors.primary, backgroundColor: colors.primary },
                { label: 'Point of Booking', data: payload.pob || [], borderColor: colors.secondary, backgroundColor: colors.secondary },
                { label: 'Administration', data: payload.admin || [], borderColor: colors.tertiary, backgroundColor: colors.tertiary },
            ].map((dataset) => ({ ...dataset, borderWidth: 2, pointRadius: 2, pointHoverRadius: 4, tension: 0.28 })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 420 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { color: colors.ink, boxWidth: 10, boxHeight: 10, padding: 18 } },
                tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${money.format(Number(context.raw) / divisor)}` } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: colors.muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }, border: { color: colors.border } },
                y: { beginAtZero: true, grid: { color: colors.border }, ticks: { color: colors.muted, callback: (value) => compactMoney.format(Number(value) / divisor) }, border: { display: false } },
            },
        },
    });
    canvas.dataset.chartReady = 'true';
}

function initializePropertyBookingSurfaces() {
    initializePropertyBookingAdmin();
    initializePropertyBookingDashboard();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePropertyBookingSurfaces, { once: true });
} else {
    initializePropertyBookingSurfaces();
}

document.addEventListener('livewire:navigated', initializePropertyBookingSurfaces);
document.addEventListener('livewire:updated', initializePropertyBookingAdmin);
window.addEventListener('aureon:dashboard-settings', () => window.requestAnimationFrame(initializePropertyBookingDashboard));
