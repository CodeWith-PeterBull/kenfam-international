const canvas = document.querySelector('[data-commerce-sales-chart]');
const payloadNode = document.querySelector('#commerce-sales-chart-data');
let salesChart = null;

function parsePayload() {
    if (!payloadNode) return null;

    try {
        return JSON.parse(payloadNode.textContent || '{}');
    } catch (_) {
        return null;
    }
}

function themeColors() {
    const styles = getComputedStyle(document.documentElement);

    return {
        primary: styles.getPropertyValue('--aureon-primary').trim() || '#70233a',
        secondary: styles.getPropertyValue('--aureon-secondary').trim() || '#28656b',
        border: styles.getPropertyValue('--aureon-border').trim() || '#e2e4e8',
        ink: styles.getPropertyValue('--aureon-ink').trim() || '#1d1d25',
        muted: styles.getPropertyValue('--aureon-muted').trim() || '#6d7079',
    };
}

function moneyFormatter(payload, compact = false) {
    return new Intl.NumberFormat(document.documentElement.lang || 'en', {
        style: 'currency',
        currency: payload.currency || 'KES',
        notation: compact ? 'compact' : 'standard',
        minimumFractionDigits: compact ? 0 : payload.decimals,
        maximumFractionDigits: compact ? 1 : payload.decimals,
    });
}

function renderSalesChart() {
    const payload = parsePayload();
    const ChartConstructor = window.Chart;

    if (!canvas || !payload || typeof ChartConstructor !== 'function') return;

    salesChart?.destroy();
    const colors = themeColors();
    const compactMoney = moneyFormatter(payload, true);
    const fullMoney = moneyFormatter(payload);
    const divisor = 10 ** Number(payload.decimals || 0);

    salesChart = new ChartConstructor(canvas, {
        type: 'line',
        data: {
            labels: payload.labels,
            datasets: [
                {
                    label: 'Online store',
                    data: payload.web,
                    borderColor: colors.primary,
                    backgroundColor: colors.primary,
                    borderWidth: 2,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    tension: 0.28,
                },
                {
                    label: 'Point of sale',
                    data: payload.pos,
                    borderColor: colors.secondary,
                    backgroundColor: colors.secondary,
                    borderWidth: 2,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    tension: 0.28,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 420 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: colors.ink, boxWidth: 10, boxHeight: 10, padding: 18 },
                },
                tooltip: {
                    callbacks: {
                        label: (context) => `${context.dataset.label}: ${fullMoney.format(Number(context.raw) / divisor)}`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: colors.muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 },
                    border: { color: colors.border },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: colors.border },
                    ticks: {
                        color: colors.muted,
                        callback: (value) => compactMoney.format(Number(value) / divisor),
                    },
                    border: { display: false },
                },
            },
        },
    });

    canvas.dataset.chartReady = 'true';
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderSalesChart, { once: true });
} else {
    renderSalesChart();
}

window.addEventListener('aureon:dashboard-settings', () => {
    window.requestAnimationFrame(renderSalesChart);
});
