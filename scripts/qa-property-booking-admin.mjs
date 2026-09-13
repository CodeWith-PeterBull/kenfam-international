import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'PropertyBooking', 'qa', 'catalog-availability');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8012';
const email = process.env.AUREON_QA_EMAIL || 'admin@aureon.test';
const password = process.env.AUREON_QA_PASSWORD || 'password';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9466);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-property-booking-qa-'));
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
        if (response.exceptionDetails) {
            throw new Error(response.exceptionDetails.exception?.description || response.exceptionDetails.text || 'Evaluation failed');
        }

        return response.result.value;
    }

    async function waitFor(expression, timeout = 10000) {
        const startedAt = Date.now();
        while (Date.now() - startedAt < timeout) {
            const value = await evaluate(expression);
            if (value) return value;
            await delay(150);
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
        if (!emailInput || !passwordInput) return false;
        emailInput.value = ${JSON.stringify(email)};
        passwordInput.value = ${JSON.stringify(password)};
        emailInput.dispatchEvent(new Event('input', { bubbles: true }));
        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
        document.querySelector('form').requestSubmit();
        return true;
    })()`);
    await loggedIn;
    const postLoginPath = await evaluate('location.pathname');
    assert(!postLoginPath.startsWith('/login'), 'Login did not leave the authentication screen');

    async function capture({ name, route, width, height, mobile, mode, reducedMotion = false }) {
        await setViewport(width, height, mobile);
        await client.send('Emulation.setEmulatedMedia', {
            media: 'screen',
            features: [{ name: 'prefers-reduced-motion', value: reducedMotion ? 'reduce' : 'no-preference' }],
        });
        await setTheme(mode);
        await navigate(`${siteUrl}${route}?qa=${name}-${Date.now()}`);
        const moduleRendered = Boolean(await waitFor("document.querySelector('[data-property-booking-admin]')"));
        assert(moduleRendered, `${name}: module root did not render at ${await evaluate('location.pathname')}`);
        if (!moduleRendered) return;
        if (route === '/admin/accommodation') {
            assert(
                Boolean(await waitFor("document.querySelector('[data-property-booking-trend-chart]')?.dataset.chartReady === 'true'")),
                `${name}: booking trend chart did not initialize`,
            );
        }

        const snapshot = await evaluate(`(() => {
            const root = document.querySelector('[data-property-booking-admin]');
            const sidebarBox = document.querySelector('#sidebar')?.getBoundingClientRect();
            const pageBox = document.querySelector('.page-wrapper')?.getBoundingClientRect();
            const contentBox = document.querySelector('.page-wrapper > .content')?.getBoundingClientRect();
            const pageHeaderBox = root?.querySelector('.page-header')?.getBoundingClientRect();
            const pageTitleBox = root?.querySelector('.page-title h4')?.getBoundingClientRect();
            const firstPanelBox = root?.querySelector('.aureon-panel')?.getBoundingClientRect();
            const headerProbeStack = pageTitleBox
                ? document.elementsFromPoint(pageTitleBox.left + 2, pageTitleBox.top + (pageTitleBox.height / 2))
                    .slice(0, 6)
                    .map((element) => element.tagName.toLowerCase()
                        + (element.id ? '#' + element.id : '')
                        + (element.classList.length ? '.' + [...element.classList].join('.') : ''))
                : [];
            const labeled = (element) => element.textContent.trim() || element.getAttribute('aria-label') || element.getAttribute('title');
            const buttons = [...root.querySelectorAll('button')].filter((button) => button.offsetParent !== null);
            const overflowText = [...root.querySelectorAll('h1,h2,h3,h4,h5,h6,p,small,strong,span,code')]
                .filter((element) => element.offsetParent !== null && element.scrollWidth > element.clientWidth + 3 && getComputedStyle(element).whiteSpace !== 'nowrap');
            const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
            const firstCard = root.querySelector('.pb-stat-card, [data-property-booking-metric]');
            const cardStyle = firstCard ? getComputedStyle(firstCard) : null;
            const chart = root?.querySelector('[data-property-booking-trend-chart]');
            let chartOpaqueSamples = 0;
            let chartRenderError = null;
            if (chart?.dataset.chartReady === 'true' && chart.width && chart.height) {
                try {
                    const pixels = chart.getContext('2d')?.getImageData(0, 0, chart.width, chart.height).data || [];
                    for (let index = 3; index < pixels.length; index += 64) {
                        if (pixels[index] > 0) chartOpaqueSamples += 1;
                    }
                } catch (error) {
                    chartRenderError = error instanceof Error ? error.message : String(error);
                }
            }

            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                title: document.querySelector('.page-title h4')?.textContent.trim(),
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                rootWidth: root?.getBoundingClientRect().width || 0,
                rootScrollWidth: root?.scrollWidth || 0,
                shellOverlap: !${mobile} && sidebarBox && pageBox ? Math.max(0, sidebarBox.right - pageBox.left) : 0,
                sidebarRight: sidebarBox?.right || 0,
                contentLeft: contentBox?.left || 0,
                rootLeft: root?.getBoundingClientRect().left || 0,
                pageHeaderLeft: pageHeaderBox?.left || 0,
                pageTitleLeft: pageTitleBox?.left || 0,
                firstPanelLeft: firstPanelBox?.left || 0,
                headerProbeStack,
                theme: document.documentElement.dataset.theme,
                loaderHidden: Boolean(document.querySelector('[data-page-loader]')?.hidden),
                workspaces: root?.querySelectorAll('.pb-workspace').length || 0,
                statCards: root?.querySelectorAll('.pb-stat-card').length || 0,
                dashboardMetrics: root?.querySelectorAll('[data-property-booking-metric]').length || 0,
                dashboard: root?.hasAttribute('data-property-booking-dashboard') || false,
                chartRuntimeLoaded: typeof window.Chart === 'function',
                chartReady: chart?.dataset.chartReady === 'true',
                chartWidth: chart?.width || 0,
                chartHeight: chart?.height || 0,
                chartOpaqueSamples,
                chartRenderError,
                panels: root?.querySelectorAll('.aureon-panel').length || 0,
                cssLoaded: Boolean(root?.querySelector('.pb-stat-grid, .pb-dashboard-metrics')),
                cardColor: cardStyle?.color,
                cardBackground: cardStyle?.backgroundColor,
                unlabeledButtons: buttons.filter((button) => !labeled(button)).length,
                duplicateIds: ids.filter((id, index) => ids.indexOf(id) !== index).length,
                overflowText: overflowText.length,
                reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
            };
        })()`);

        diagnostics.push(snapshot);
        assert(snapshot.path === route, `${name}: wrong route rendered`);
        assert(snapshot.title, `${name}: page title is missing`);
        assert(snapshot.viewportWidth === snapshot.documentWidth, `${name}: document overflows the viewport`);
        assert(snapshot.rootScrollWidth <= snapshot.rootWidth + 2, `${name}: module root overflows its workspace`);
        assert(snapshot.shellOverlap <= 1, `${name}: sidebar overlaps the page shell`);
        assert(mobile || snapshot.rootLeft >= snapshot.sidebarRight + 12, `${name}: module workspace lacks its desktop sidebar gutter`);
        assert(!snapshot.headerProbeStack.some((selector) => selector.includes('sidebar')), `${name}: sidebar content occludes the page heading`);
        assert(snapshot.theme === mode, `${name}: expected ${mode} theme`);
        assert(snapshot.loaderHidden, `${name}: loader did not settle`);
        assert(snapshot.workspaces >= 1 || snapshot.dashboard, `${name}: admin workspace is incomplete`);
        assert(snapshot.statCards + snapshot.dashboardMetrics >= 4, `${name}: summary metrics are incomplete`);
        if (snapshot.dashboard) {
            assert(snapshot.chartRuntimeLoaded, `${name}: Chart.js runtime is missing`);
            assert(snapshot.chartReady, `${name}: booking trend chart is not ready`);
            assert(snapshot.chartWidth > 0 && snapshot.chartHeight > 0, `${name}: booking trend canvas has no render area`);
            assert(!snapshot.chartRenderError, `${name}: booking trend canvas could not be inspected: ${snapshot.chartRenderError}`);
            assert(snapshot.chartOpaqueSamples > 10, `${name}: booking trend canvas is visually blank`);
        }
        assert(snapshot.panels >= 1, `${name}: expected workspace panels are missing`);
        assert(snapshot.cssLoaded, `${name}: module stylesheet did not load`);
        assert(snapshot.cardColor !== snapshot.cardBackground, `${name}: card text is not distinguishable from its surface`);
        assert(snapshot.unlabeledButtons === 0, `${name}: visible button without an accessible name`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate element IDs found`);
        assert(snapshot.overflowText === 0, `${name}: visible text overflows its container`);
        assert(snapshot.reducedMotion === reducedMotion, `${name}: reduced-motion preference was not applied`);

        const screenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    const captures = [
        { name: 'desktop-light-properties', route: '/admin/accommodation/properties', width: 1440, height: 1000, mobile: false, mode: 'light' },
        { name: 'desktop-dark-amenities', route: '/admin/accommodation/amenities', width: 1280, height: 900, mobile: false, mode: 'dark' },
        { name: 'tablet-light-units', route: '/admin/accommodation/units', width: 820, height: 1080, mobile: false, mode: 'light' },
        { name: 'mobile-dark-rates', route: '/admin/accommodation/rates', width: 390, height: 844, mobile: true, mode: 'dark' },
        { name: 'mobile-light-availability-reduced-motion', route: '/admin/accommodation/availability', width: 390, height: 844, mobile: true, mode: 'light', reducedMotion: true },
        { name: 'operations-dashboard-desktop-light', route: '/admin/accommodation', width: 1440, height: 1000, mobile: false, mode: 'light' },
        { name: 'operations-bookings-desktop-dark', route: '/admin/accommodation/bookings', width: 1280, height: 900, mobile: false, mode: 'dark' },
        { name: 'operations-guests-mobile-light', route: '/admin/accommodation/guests', width: 390, height: 844, mobile: true, mode: 'light' },
        { name: 'operations-readiness-tablet-dark-reduced-motion', route: '/admin/accommodation/readiness', width: 820, height: 1080, mobile: false, mode: 'dark', reducedMotion: true },
    ];

    for (const captureDefinition of captures) await capture(captureDefinition);

    await setViewport(1440, 1000, false);
    await setTheme('light');
    await navigate(`${siteUrl}/admin/accommodation/properties?qa=modal-${Date.now()}`);
    await evaluate(`(() => {
        const button = document.querySelector('#property-booking-property-manager button[aria-label^="Edit "]');
        button?.click();
    })()`);
    assert(Boolean(await waitFor("document.querySelector('.modal.show[aria-modal=\"true\"]')")), 'Property form modal did not open');
    const modal = await evaluate(`(() => {
        const element = document.querySelector('.modal.show');
        const controls = [...element.querySelectorAll('input:not([type="hidden"]),select,textarea')];
        return {
            controls: controls.length,
            unlabeledControls: controls.filter((control) => !control.id || !element.querySelector('label[for="' + control.id + '"]')).length,
            top: element.getBoundingClientRect().top,
            right: element.getBoundingClientRect().right,
            viewportWidth: innerWidth,
        };
    })()`);
    diagnostics.push({ name: 'property-form-modal', ...modal });
    assert(modal.controls > 20 && modal.unlabeledControls === 0, 'Property form controls are incomplete or unlabeled');
    assert(modal.top >= 0 && modal.right <= modal.viewportWidth + 1, 'Property form modal is outside the viewport');
    const modalScreenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
    await writeFile(path.join(outputDirectory, 'desktop-light-property-form-modal.png'), Buffer.from(modalScreenshot.data, 'base64'));

    await evaluate("document.querySelector('.pb-form-media-editor')?.scrollIntoView({ block: 'start' })");
    await delay(180);
    const propertyMediaForm = await evaluate(`(() => {
        const section = document.querySelector('.pb-form-media-editor');
        const canvas = section?.querySelector('.pb-form-media-canvas')?.getBoundingClientRect();
        const controls = [...(section?.querySelectorAll('input') || [])];
        return {
            recordName: document.querySelector('#property-form-name')?.value || '',
            canvasWidth: canvas?.width || 0,
            canvasHeight: canvas?.height || 0,
            currentCoverRendered: Boolean(section?.querySelector('.pb-form-media-canvas > img')),
            currentGalleryCount: section?.querySelectorAll('.pb-form-gallery-existing img').length || 0,
            galleryMultiple: Boolean(section?.querySelector('#property-form-gallery[multiple]')),
            unlabeledControls: controls.filter((control) => !control.id || !section.querySelector('label[for="' + control.id + '"]')).length,
            sectionWidth: section?.getBoundingClientRect().width || 0,
            sectionScrollWidth: section?.scrollWidth || 0,
        };
    })()`);
    diagnostics.push({ name: 'property-form-media', ...propertyMediaForm });
    assert(propertyMediaForm.canvasWidth > 700 && propertyMediaForm.canvasHeight >= 250, 'Property media canvas is not presentation-sized');
    assert(propertyMediaForm.currentCoverRendered, 'Property CRUD did not render the seeded cover image');
    assert(propertyMediaForm.currentGalleryCount === 4, 'Property CRUD did not render the complete seeded gallery');
    assert(propertyMediaForm.galleryMultiple, 'Property CRUD gallery does not accept multiple files');
    assert(propertyMediaForm.unlabeledControls === 0, 'Property CRUD media controls are not completely labeled');
    assert(propertyMediaForm.sectionScrollWidth <= propertyMediaForm.sectionWidth + 2, 'Property CRUD media section overflows');
    const propertyMediaScreenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
    await writeFile(path.join(outputDirectory, 'desktop-light-property-form-media.png'), Buffer.from(propertyMediaScreenshot.data, 'base64'));

    await setTheme('dark');
    await navigate(`${siteUrl}/admin/accommodation/units?qa=unit-media-${Date.now()}`);
    await evaluate(`(() => {
        const button = document.querySelector('#property-booking-unit-type-manager button[aria-label^="Edit "]');
        button?.click();
    })()`);
    assert(Boolean(await waitFor("document.querySelector('.modal.show[aria-modal=\"true\"]')")), 'Unit-type form modal did not open');
    await evaluate("document.querySelector('.pb-form-media-editor')?.scrollIntoView({ block: 'start' })");
    await delay(180);
    const unitMediaForm = await evaluate(`(() => {
        const section = document.querySelector('#property-booking-unit-type-manager .pb-form-media-editor');
        const canvas = section?.querySelector('.pb-form-media-canvas')?.getBoundingClientRect();
        const controls = [...(section?.querySelectorAll('input') || [])];
        return {
            recordName: document.querySelector('#type-form-name')?.value || '',
            canvasWidth: canvas?.width || 0,
            canvasHeight: canvas?.height || 0,
            currentCoverRendered: Boolean(section?.querySelector('.pb-form-media-canvas > img')),
            currentGalleryCount: section?.querySelectorAll('.pb-form-gallery-existing img').length || 0,
            galleryMultiple: Boolean(section?.querySelector('#type-form-gallery[multiple]')),
            unlabeledControls: controls.filter((control) => !control.id || !section.querySelector('label[for="' + control.id + '"]')).length,
            sectionWidth: section?.getBoundingClientRect().width || 0,
            sectionScrollWidth: section?.scrollWidth || 0,
            theme: document.documentElement.dataset.theme,
        };
    })()`);
    diagnostics.push({ name: 'unit-type-form-media', ...unitMediaForm });
    assert(unitMediaForm.canvasWidth > 700 && unitMediaForm.canvasHeight >= 250, 'Unit-type media canvas is not presentation-sized');
    assert(unitMediaForm.currentCoverRendered, 'Unit-type CRUD did not render the seeded cover image');
    assert(unitMediaForm.galleryMultiple, 'Unit-type CRUD gallery does not accept multiple files');
    assert(unitMediaForm.unlabeledControls === 0, 'Unit-type CRUD media controls are not completely labeled');
    assert(unitMediaForm.sectionScrollWidth <= unitMediaForm.sectionWidth + 2, 'Unit-type CRUD media section overflows');
    assert(unitMediaForm.theme === 'dark', 'Unit-type media form did not retain dark mode');
    const unitMediaScreenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
    await writeFile(path.join(outputDirectory, 'desktop-dark-unit-type-form-media.png'), Buffer.from(unitMediaScreenshot.data, 'base64'));

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
    process.stdout.write(`Property Booking admin QA passed with ${captures.length + 3} captures.\n`);
} finally {
    client?.close();
    const browserExited = new Promise((resolve) => browser.once('exit', resolve));
    browser.kill();
    await Promise.race([browserExited, delay(3000)]);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => undefined);
}
