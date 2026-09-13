import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'dev', 'commerce-dashboard-qa');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8012';
const email = process.env.AUREON_QA_EMAIL || 'admin@aureon.test';
const password = process.env.AUREON_QA_PASSWORD || 'password';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9462);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-commerce-dashboard-qa-'));
const browser = spawn(browserPath, [
    '--headless=new',
    '--disable-gpu',
    '--disable-background-networking',
    '--disable-extensions',
    '--hide-scrollbars',
    '--no-first-run',
    `--remote-debugging-port=${debuggingPort}`,
    `--user-data-dir=${profileDirectory}`,
    'about:blank',
], { stdio: 'ignore' });

const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function fetchJson(url, options) {
    const response = await fetch(url, options);
    if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);

    return response.json();
}

async function waitForBrowser() {
    for (let attempt = 0; attempt < 60; attempt += 1) {
        try {
            return await fetchJson(`http://127.0.0.1:${debuggingPort}/json/version`);
        } catch {
            await delay(200);
        }
    }

    throw new Error('Browser debugging endpoint did not become ready');
}

class CdpClient {
    constructor(webSocketUrl) {
        this.webSocket = new WebSocket(webSocketUrl);
        this.nextId = 1;
        this.pending = new Map();
        this.listeners = new Map();
    }

    async connect() {
        await new Promise((resolve, reject) => {
            this.webSocket.addEventListener('open', resolve, { once: true });
            this.webSocket.addEventListener('error', reject, { once: true });
        });
        this.webSocket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);
            if (message.id) {
                const pending = this.pending.get(message.id);
                if (!pending) return;
                this.pending.delete(message.id);
                if (message.error) pending.reject(new Error(`${pending.method}: ${message.error.message}`));
                else pending.resolve(message.result);
                return;
            }
            this.listeners.get(message.method)?.forEach((listener) => listener(message.params));
        });
    }

    send(method, params = {}) {
        const id = this.nextId;
        this.nextId += 1;

        return new Promise((resolve, reject) => {
            this.pending.set(id, { method, resolve, reject });
            this.webSocket.send(JSON.stringify({ id, method, params }));
        });
    }

    once(method, timeout = 45000) {
        return new Promise((resolve, reject) => {
            const listener = (params) => {
                clearTimeout(timer);
                this.listeners.get(method)?.delete(listener);
                resolve(params);
            };
            const timer = setTimeout(() => {
                this.listeners.get(method)?.delete(listener);
                reject(new Error(`Timed out waiting for ${method}`));
            }, timeout);
            if (!this.listeners.has(method)) this.listeners.set(method, new Set());
            this.listeners.get(method).add(listener);
        });
    }

    close() {
        this.webSocket.close();
    }
}

let client;
const diagnostics = [];
const failures = [];
const runtimeErrors = [];
const networkErrors = [];
const assert = (condition, message) => { if (!condition) failures.push(message); };

try {
    await waitForBrowser();
    const target = await fetchJson(`http://127.0.0.1:${debuggingPort}/json/new?about:blank`, { method: 'PUT' });
    client = new CdpClient(target.webSocketDebuggerUrl);
    await client.connect();
    await Promise.all([
        client.send('Page.enable'),
        client.send('Runtime.enable'),
        client.send('Network.enable'),
        client.send('Log.enable'),
    ]);

    const listen = (method, handler) => {
        if (!client.listeners.has(method)) client.listeners.set(method, new Set());
        client.listeners.get(method).add(handler);
    };
    listen('Runtime.exceptionThrown', (event) => runtimeErrors.push(event.exceptionDetails.text || 'Runtime exception'));
    listen('Log.entryAdded', (event) => {
        if (event.entry.level === 'error') runtimeErrors.push(event.entry.text);
    });
    listen('Network.responseReceived', (event) => {
        if (event.response.status >= 400) networkErrors.push(`${event.response.status} ${event.response.url}`);
    });

    async function evaluate(expression, awaitPromise = false) {
        const response = await client.send('Runtime.evaluate', { expression, awaitPromise, returnByValue: true });
        if (response.exceptionDetails) throw new Error(response.exceptionDetails.text || 'Evaluation failed');

        return response.result.value;
    }

    async function waitFor(expression, timeout = 9000) {
        const startedAt = Date.now();
        while (Date.now() - startedAt < timeout) {
            const value = await evaluate(expression);
            if (value) return value;
            await delay(120);
        }

        return null;
    }

    async function navigate(url) {
        const loaded = client.once('Page.loadEventFired');
        await client.send('Page.navigate', { url });
        await loaded;
        await evaluate('document.fonts.ready', true);
        await evaluate(`new Promise((resolve) => {
            const loader = document.querySelector('[data-page-loader]');
            if (!loader || loader.hidden) return resolve(true);
            window.addEventListener('aureon:page-loader-dismissed', () => resolve(true), { once: true });
            setTimeout(() => resolve(false), 9000);
        })`, true);
        await delay(180);
    }

    async function setViewport(width, height, mobile) {
        await client.send('Emulation.clearDeviceMetricsOverride');
        await client.send('Emulation.setDeviceMetricsOverride', {
            width,
            height,
            deviceScaleFactor: 1,
            mobile,
            screenWidth: width,
            screenHeight: height,
        });
        await client.send('Emulation.setTouchEmulationEnabled', { enabled: mobile, maxTouchPoints: mobile ? 5 : 1 });
    }

    async function setTheme(mode) {
        await evaluate(`(() => {
            const key = 'laravel-aureon-dashboard-settings';
            let value = {};
            try { value = JSON.parse(localStorage.getItem(key) || '{}'); } catch { value = {}; }
            localStorage.setItem(key, JSON.stringify({ ...value, mode: ${JSON.stringify(mode)} }));
        })()`);
    }

    await setViewport(1440, 1000, false);
    await navigate(`${siteUrl}/login`);
    const loggedIn = client.once('Page.loadEventFired');
    await evaluate(`(() => {
        const emailInput = document.querySelector('input[name="email"]');
        const passwordInput = document.querySelector('input[name="password"]');
        emailInput.value = ${JSON.stringify(email)};
        passwordInput.value = ${JSON.stringify(password)};
        emailInput.dispatchEvent(new Event('input', { bubbles: true }));
        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
        document.querySelector('form').requestSubmit();
    })()`);
    await loggedIn;

    async function capture(name, width, height, mobile, mode, range, reducedMotion = false) {
        await setViewport(width, height, mobile);
        await client.send('Emulation.setEmulatedMedia', {
            media: 'screen',
            features: [{ name: 'prefers-reduced-motion', value: reducedMotion ? 'reduce' : 'no-preference' }],
        });
        await setTheme(mode);
        await navigate(`${siteUrl}/admin/commerce?range=${range}&qa=${name}-${Date.now()}`);
        assert(Boolean(await waitFor("document.querySelector('[data-commerce-sales-chart]')?.dataset.chartReady === 'true'")), `${name}: sales chart did not initialize`);
        await delay(reducedMotion ? 100 : 520);
        await evaluate('window.scrollTo(0, 0)');

        const snapshot = await evaluate(`(() => {
            const root = document.querySelector('[data-commerce-dashboard]');
            const canvas = document.querySelector('[data-commerce-sales-chart]');
            const context = canvas?.getContext('2d');
            const pixels = context ? context.getImageData(0, 0, canvas.width, canvas.height).data : [];
            let paintedPixels = 0;
            for (let index = 3; index < pixels.length; index += 4) {
                if (pixels[index] > 0) paintedPixels += 1;
            }
            const metrics = [...document.querySelectorAll('[data-commerce-metric]')];
            const metricBoxes = metrics.map((metric) => metric.getBoundingClientRect());
            const sidebarBox = document.querySelector('#sidebar')?.getBoundingClientRect();
            const pageBox = document.querySelector('.page-wrapper')?.getBoundingClientRect();
            const contentBox = document.querySelector('.page-wrapper > .content')?.getBoundingClientRect();
            const labeled = (element) => element.textContent.trim() || element.getAttribute('aria-label') || element.getAttribute('title');
            const overflowElements = [...document.querySelectorAll('.commerce-dashboard-metric__copy > *, .commerce-stock-row h4, .commerce-stock-row p, .commerce-till-row h4, .commerce-till-row p')]
                .filter((element) => element.scrollWidth > element.clientWidth + 2 && getComputedStyle(element).overflowWrap === 'normal');
            const rangeLink = document.querySelector('.commerce-dashboard-range a[aria-current="page"]');
            const dashboardLinks = [...root.querySelectorAll('a[href]')];
            const cashierHistory = root.querySelector('[data-cashier-sales-history][data-surface="dashboard"]');
            const shopLinks = dashboardLinks.filter((link) => new URL(link.href).pathname === '/shop');

            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                range: new URL(location.href).searchParams.get('range'),
                activeRange: rangeLink?.textContent.trim(),
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                sidebar: sidebarBox ? { left: sidebarBox.left, right: sidebarBox.right, width: sidebarBox.width } : null,
                pageLeft: pageBox?.left ?? null,
                contentLeft: contentBox?.left ?? null,
                rootLeft: root?.getBoundingClientRect().left ?? null,
                shellOverlap: !${mobile} && sidebarBox && pageBox ? Math.max(0, sidebarBox.right - pageBox.left) : 0,
                bodyClasses: document.body.className,
                theme: document.documentElement.dataset.theme,
                loaderHidden: Boolean(document.querySelector('[data-page-loader]')?.hidden),
                metrics: metrics.length,
                stableMetrics: metricBoxes.every((box) => box.width >= 180 && box.height >= 100),
                panels: document.querySelectorAll('.commerce-dashboard-panel').length,
                chartReady: canvas?.dataset.chartReady === 'true',
                chartWidth: canvas?.width || 0,
                chartHeight: canvas?.height || 0,
                paintedPixels,
                fallbackRows: document.querySelectorAll('.commerce-chart-data tbody tr').length,
                orderRows: document.querySelectorAll('.commerce-dashboard-table tbody tr').length,
                quickActions: document.querySelectorAll('.commerce-dashboard-welcome__actions a').length,
                cashierHistory: Boolean(cashierHistory),
                cashierHistoryRows: cashierHistory?.querySelectorAll('.cashier-sales-history__sale').length || 0,
                cashierHistoryMetrics: cashierHistory?.querySelectorAll('.cashier-sales-history__metrics > div').length || 0,
                cashierHistoryOverflow: Boolean(cashierHistory && cashierHistory.scrollWidth > cashierHistory.clientWidth + 2),
                shopLinks: shopLinks.length,
                safeShopLinks: shopLinks.every((link) => link.target === '_blank' && link.rel.includes('noopener')),
                dashboardLinks: dashboardLinks.length,
                invalidLinks: dashboardLinks.filter((link) => !link.href || link.href.startsWith('javascript:')).length,
                unlabeledLinks: dashboardLinks.filter((link) => !labeled(link)).length,
                duplicateIds: [...document.querySelectorAll('[id]')].map((element) => element.id).filter((id, index, ids) => ids.indexOf(id) !== index).length,
                overflowElements: overflowElements.length,
                reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
            };
        })()`);

        diagnostics.push(snapshot);
        assert(snapshot.path === '/admin/commerce', `${name}: wrong route rendered`);
        assert(snapshot.range === String(range), `${name}: reporting range was not retained`);
        assert(snapshot.activeRange === `${range} days`, `${name}: selected range is not exposed accessibly`);
        assert(snapshot.viewportWidth === snapshot.documentWidth, `${name}: document overflows viewport`);
        assert(snapshot.shellOverlap <= 1, `${name}: fixed sidebar overlaps the dashboard workspace`);
        assert(snapshot.theme === mode, `${name}: expected ${mode} theme`);
        assert(snapshot.loaderHidden, `${name}: loader did not settle`);
        assert(snapshot.metrics === 8 && snapshot.stableMetrics, `${name}: metric cards are incomplete or unstable`);
        assert(snapshot.panels === 6, `${name}: expected six operational panels`);
        assert(snapshot.chartReady && snapshot.chartWidth > 100 && snapshot.chartHeight > 100, `${name}: chart canvas is not ready`);
        assert(snapshot.paintedPixels > 100, `${name}: chart canvas is blank`);
        assert(snapshot.fallbackRows === range, `${name}: accessible chart table does not match the selected range`);
        assert(snapshot.orderRows > 0, `${name}: recent-order state did not render`);
        assert(snapshot.quickActions >= 4, `${name}: authorized quick actions are incomplete`);
        assert(snapshot.cashierHistory && snapshot.cashierHistoryRows > 0 && snapshot.cashierHistoryMetrics === 3, `${name}: cashier sales history is incomplete`);
        assert(!snapshot.cashierHistoryOverflow, `${name}: cashier sales history overflows its dashboard surface`);
        assert(snapshot.shopLinks >= 1 && snapshot.safeShopLinks, `${name}: shop frontend links are missing or unsafe`);
        assert(snapshot.dashboardLinks > 10 && snapshot.invalidLinks === 0, `${name}: operational links are incomplete`);
        assert(snapshot.unlabeledLinks === 0, `${name}: link without an accessible name`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate element IDs found`);
        assert(snapshot.overflowElements === 0, `${name}: dashboard text overflows its container`);
        assert(snapshot.reducedMotion === reducedMotion, `${name}: reduced-motion preference was not applied`);

        const viewportScreenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}-viewport.png`), Buffer.from(viewportScreenshot.data, 'base64'));

        const screenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: true,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    await capture('desktop-light-30-days', 1440, 1000, false, 'light', 30);
    await capture('laptop-dark-30-days', 1080, 900, false, 'dark', 30);
    await capture('tablet-light-7-days', 820, 1080, false, 'light', 7);
    await capture('mobile-dark-90-days-reduced-motion', 390, 844, true, 'dark', 90, true);

    assert(runtimeErrors.length === 0, `Runtime errors: ${runtimeErrors.join(' | ')}`);
    assert(networkErrors.length === 0, `Network errors: ${networkErrors.join(' | ')}`);
    await writeFile(path.join(outputDirectory, 'diagnostics.json'), JSON.stringify({
        siteUrl,
        diagnostics,
        runtimeErrors,
        networkErrors,
        failures,
    }, null, 2));

    if (failures.length > 0) throw new Error(failures.join('\n'));
    process.stdout.write(`Commerce dashboard QA passed with ${diagnostics.length} captures.\n`);
} finally {
    client?.close();
    const browserExited = new Promise((resolve) => browser.once('exit', resolve));
    browser.kill();
    await Promise.race([browserExited, delay(3000)]);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => undefined);
}
