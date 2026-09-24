/** Browser UAT for TravelTours catalog and taxonomy administration. */
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const qaPhase = process.env.AUREON_QA_PHASE || '';
const outputDirectory = path.join(root, '.docs', 'TravelTours', 'qa', ['k2d', 'k2e', 'k3a'].includes(qaPhase) ? `admin-${qaPhase}` : 'admin');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8013';
const email = process.env.AUREON_QA_EMAIL || 'admin@kenfam.test';
const password = process.env.AUREON_QA_PASSWORD || 'password';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9493);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'kenfam-travel-admin-qa-'));
const browser = spawn(browserPath, [
    '--headless=new', '--disable-gpu', '--disable-background-networking', '--disable-extensions',
    '--hide-scrollbars', '--no-first-run', `--remote-debugging-port=${debuggingPort}`,
    `--user-data-dir=${profileDirectory}`, 'about:blank',
], { stdio: 'ignore' });
const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function fetchJson(url, options) {
    const response = await fetch(url, options);
    if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);
    return response.json();
}

async function waitForBrowser() {
    for (let attempt = 0; attempt < 60; attempt += 1) {
        try { return await fetchJson(`http://127.0.0.1:${debuggingPort}/json/version`); } catch { await delay(200); }
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

    close() { this.webSocket.close(); }
}

let client;
const diagnostics = [];
const failures = [];
const runtimeErrors = [];
const networkErrors = [];
const externalWarnings = [];
const assert = (condition, message) => { if (!condition) failures.push(message); };

try {
    await waitForBrowser();
    const target = await fetchJson(`http://127.0.0.1:${debuggingPort}/json/new?about:blank`, { method: 'PUT' });
    client = new CdpClient(target.webSocketDebuggerUrl);
    await client.connect();
    await Promise.all([client.send('Page.enable'), client.send('Runtime.enable'), client.send('Network.enable'), client.send('Log.enable')]);
    const listen = (method, handler) => {
        if (!client.listeners.has(method)) client.listeners.set(method, new Set());
        client.listeners.get(method).add(handler);
    };
    listen('Runtime.exceptionThrown', (event) => runtimeErrors.push(event.exceptionDetails.text || 'Runtime exception'));
    listen('Log.entryAdded', (event) => {
        if (event.entry.level === 'error') {
            const message = `${event.entry.text}${event.entry.url ? ` (${event.entry.url})` : ''}`;
            if (event.entry.url?.startsWith('https://fonts.googleapis.com/')) externalWarnings.push(message);
            else runtimeErrors.push(message);
        }
    });
    listen('Network.responseReceived', (event) => { if (event.response.status >= 400) networkErrors.push(`${event.response.status} ${event.response.url}`); });

    async function evaluate(expression, awaitPromise = false) {
        const response = await client.send('Runtime.evaluate', { expression, awaitPromise, returnByValue: true });
        if (response.exceptionDetails) throw new Error(response.exceptionDetails.exception?.description || response.exceptionDetails.text || 'Evaluation failed');
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
        await client.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile, screenWidth: width, screenHeight: height });
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
    assert(!(await evaluate('location.pathname')).startsWith('/login'), 'Login did not leave the authentication screen');

    await navigate(`${siteUrl}/admin/travel/catalog`);
    const editPath = await evaluate(`document.querySelector('#travel-tour-catalog a[title="Edit tour"]')?.getAttribute('href')`);
    assert(Boolean(editPath), 'The seeded catalog did not expose an editable tour');

    async function capture({ name, route, width, height, mobile, mode, reducedMotion = false, before = null, expected = null, after = null, afterExpected = null, scrollSelector = null }) {
        await setViewport(width, height, mobile);
        await client.send('Emulation.setEmulatedMedia', { media: 'screen', features: [{ name: 'prefers-reduced-motion', value: reducedMotion ? 'reduce' : 'no-preference' }] });
        await setTheme(mode);
        await navigate(`${siteUrl}${route}${route.includes('?') ? '&' : '?'}qa=${name}-${Date.now()}`);
        assert(Boolean(await waitFor("document.querySelector('.travel-admin')")), `${name}: TravelTours admin root did not render`);
        if (before) {
            await evaluate(before);
            assert(Boolean(await waitFor(expected, 12000)), `${name}: requested interactive state did not open`);
            if (name === 'tablet-dark-destination-media') await waitFor("document.querySelector('#travel-destination-manager .modal.show .travel-admin-media-item img')?.complete", 8000);
            await delay(180);
        }
        if (after) {
            await evaluate(after);
            assert(Boolean(await waitFor(afterExpected, 12000)), `${name}: requested child form did not open`);
        }
        if (scrollSelector) {
            await evaluate(`document.querySelector(${JSON.stringify(scrollSelector)})?.scrollIntoView({ block: 'start' })`);
            await delay(180);
        }
        const snapshot = await evaluate(`(() => {
            const root = document.querySelector('.travel-admin');
            const sidebarBox = document.querySelector('#sidebar')?.getBoundingClientRect();
            const pageBox = document.querySelector('.page-wrapper')?.getBoundingClientRect();
            const content = document.querySelector('.page-wrapper.aureon-dashboard > .content');
            const contentStyle = content ? getComputedStyle(content) : null;
            const titleBox = root?.querySelector('.page-title h4')?.getBoundingClientRect();
            const panel = root?.querySelector('.aureon-panel');
            const panelStyle = panel ? getComputedStyle(panel) : null;
            const buttons = [...(root?.querySelectorAll('button') || [])].filter((button) => button.offsetParent !== null);
            const labeled = (element) => element.textContent.trim() || element.getAttribute('aria-label') || element.getAttribute('title');
            const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
            const overflowText = [...(root?.querySelectorAll('h1,h2,h3,h4,h5,h6,p,small,strong,span,label') || [])]
                .filter((element) => element.offsetParent !== null && element.clientWidth > 0 && element.scrollWidth > element.clientWidth + 3 && getComputedStyle(element).whiteSpace !== 'nowrap');
            const modal = root?.querySelector('.modal.show[aria-modal="true"]')?.getBoundingClientRect();
            const primaryProbe = document.createElement('span');
            primaryProbe.style.color = 'var(--aureon-primary)';
            document.body.append(primaryProbe);
            const primaryColor = getComputedStyle(primaryProbe).color;
            primaryProbe.remove();
            return {
                name: ${JSON.stringify(name)}, path: location.pathname, title: root?.querySelector('.page-title h4')?.textContent.trim(),
                viewportWidth: innerWidth, documentWidth: document.documentElement.scrollWidth,
                rootWidth: root?.getBoundingClientRect().width || 0, rootScrollWidth: root?.scrollWidth || 0,
                shellOverlap: !${mobile} && sidebarBox && pageBox ? Math.max(0, sidebarBox.right - pageBox.left) : 0,
                sidebarRight: sidebarBox?.right || 0, rootLeft: root?.getBoundingClientRect().left || 0,
                headerOccluded: titleBox ? document.elementsFromPoint(titleBox.left + 2, titleBox.top + titleBox.height / 2).some((element) => element.id === 'sidebar') : false,
                theme: document.documentElement.dataset.theme, loaderHidden: !document.querySelector('[data-page-loader]') || Boolean(document.querySelector('[data-page-loader]')?.hidden),
                contentBackground: contentStyle?.backgroundColor || null,
                contentPatternImage: contentStyle?.backgroundImage || null,
                panels: root?.querySelectorAll('.aureon-panel').length || 0,
                cssLoaded: [...document.styleSheets].some((sheet) => sheet.href?.includes('admin-') || sheet.href?.includes('admin.css')),
                panelColor: panelStyle?.color, panelBackground: panelStyle?.backgroundColor,
                unlabeledButtons: buttons.filter((button) => !labeled(button)).length,
                duplicateIds: ids.filter((id, index) => ids.indexOf(id) !== index).length,
                overflowText: overflowText.map((element) => element.textContent.trim().slice(0, 80)),
                reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
                modalPresent: Boolean(modal),
                modalInsideViewport: !modal || (modal.left >= 0 && modal.right <= innerWidth && modal.top >= 0 && modal.bottom <= innerHeight),
                modalFocusInside: !modal || Boolean(root?.querySelector('.travel-child-modal')?.contains(document.activeElement)),
                mediaCoverWidth: root?.querySelector('.modal.show .travel-admin-media-item img')?.naturalWidth || 0,
                mediaCoverSrc: root?.querySelector('.modal.show .travel-admin-media-item img')?.currentSrc || null,
                selectedEditorTab: root?.querySelector('#travel-tour-editor [role="tab"][aria-selected="true"]')?.textContent.trim() || null,
                childFormPresent: Boolean(root?.querySelector('.travel-child-modal form')),
                dashboardStats: root?.querySelectorAll('.travel-dashboard-stat').length || 0,
                dashboardLinks: root?.querySelectorAll('.travel-dashboard a[href]').length || 0,
                mobileMenuColor: getComputedStyle(document.querySelector('#mobile_btn .bar-icon span') || document.documentElement).backgroundColor,
                primaryColor,
            };
        })()`);
        diagnostics.push(snapshot);
        assert(snapshot.path === route.split('?')[0], `${name}: wrong route rendered`);
        assert(Boolean(snapshot.title), `${name}: page title is missing`);
        assert(snapshot.viewportWidth === snapshot.documentWidth, `${name}: document overflows viewport`);
        assert(snapshot.rootScrollWidth <= snapshot.rootWidth + 2, `${name}: module root overflows workspace`);
        assert(snapshot.shellOverlap <= 1, `${name}: sidebar overlaps page shell`);
        assert(mobile || snapshot.rootLeft >= snapshot.sidebarRight + 12, `${name}: desktop sidebar gutter is missing`);
        assert(!snapshot.headerOccluded, `${name}: sidebar occludes page heading`);
        assert(snapshot.theme === mode, `${name}: expected ${mode} theme`);
        assert(
            mode === 'light'
                ? snapshot.contentPatternImage?.includes('rocking_grid_bg.webp')
                : !snapshot.contentPatternImage?.includes('rocking_grid_bg.webp'),
            `${name}: dashboard content pattern did not match the ${mode} theme`,
        );
        assert(snapshot.loaderHidden, `${name}: page loader did not settle`);
        assert(snapshot.panels >= 1, `${name}: expected panel is missing`);
        assert(snapshot.cssLoaded, `${name}: module stylesheet is missing`);
        assert(snapshot.panelColor !== snapshot.panelBackground, `${name}: panel text blends into its background`);
        assert(snapshot.unlabeledButtons === 0, `${name}: visible button lacks an accessible name`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate element IDs found`);
        assert(snapshot.overflowText.length === 0, `${name}: visible text overflow: ${snapshot.overflowText.join(' | ')}`);
        assert(snapshot.reducedMotion === reducedMotion, `${name}: reduced-motion preference was not applied`);
        assert(snapshot.modalInsideViewport, `${name}: open modal exceeds the viewport`);
        if (expected?.includes('modal.show')) assert(snapshot.modalPresent, `${name}: modal is absent from the capture`);
        if (afterExpected?.includes('travel-child-modal')) {
            assert(snapshot.modalPresent && snapshot.childFormPresent, `${name}: contextual child dialog is absent`);
            assert(snapshot.modalFocusInside, `${name}: keyboard focus did not enter the child dialog`);
        }
        if (name === 'tablet-dark-destination-media') assert(snapshot.mediaCoverWidth > 0, `${name}: destination cover did not render`);
        if (route === '/admin/travel') {
            assert(snapshot.dashboardStats === 4, `${name}: travel dashboard does not render four at-glance statistics`);
            assert(snapshot.dashboardLinks >= 8, `${name}: travel dashboard operational links are incomplete`);
        }
        if (name === 'mobile-dark-travel-dashboard') assert(snapshot.mobileMenuColor === snapshot.primaryColor, `${name}: mobile menu trigger does not inherit the active primary color`);
        const screenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    async function captureBookingDesk({ name, width, height, mobile, mode }) {
        await setViewport(width, height, mobile);
        await client.send('Emulation.setEmulatedMedia', { media: 'screen', features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
        await setTheme(mode);
        await navigate(`${siteUrl}/travel-booking-desk?qa=${name}-${Date.now()}`);
        assert(Boolean(await waitFor("document.body.classList.contains('travel-tours-pob-shell')")), `${name}: booking-desk shell did not render`);

        const snapshot = await evaluate(`(() => {
            const bodyStyle = getComputedStyle(document.body);
            const main = document.querySelector('#pob-main');
            const buttons = [...document.querySelectorAll('button')].filter((button) => button.offsetParent !== null);
            const named = (button) => button.textContent.trim() || button.getAttribute('aria-label') || button.getAttribute('title');
            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                theme: document.documentElement.dataset.theme,
                patternImage: bodyStyle.backgroundImage,
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                mainPresent: Boolean(main),
                topbarPresent: Boolean(document.querySelector('.pob-topbar')),
                unlabeledButtons: buttons.filter((button) => !named(button)).length,
            };
        })()`);
        diagnostics.push(snapshot);
        assert(snapshot.path === '/travel-booking-desk', `${name}: wrong booking-desk route rendered`);
        assert(snapshot.theme === mode, `${name}: expected ${mode} booking-desk theme`);
        assert(snapshot.viewportWidth === snapshot.documentWidth, `${name}: booking desk overflows the viewport`);
        assert(snapshot.mainPresent && snapshot.topbarPresent, `${name}: booking-desk base components are incomplete`);
        assert(snapshot.unlabeledButtons === 0, `${name}: visible booking-desk button lacks an accessible name`);
        assert(
            mode === 'light'
                ? snapshot.patternImage.includes('rocking_grid_bg.webp')
                : !snapshot.patternImage.includes('rocking_grid_bg.webp'),
            `${name}: booking-desk pattern did not match the ${mode} theme`,
        );

        const screenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    const editorRoute = new URL(editPath, siteUrl).pathname;
    const captures = [
        { name: 'desktop-light-travel-dashboard', route: '/admin/travel', width: 1440, height: 1000, mobile: false, mode: 'light' },
        { name: 'mobile-dark-travel-dashboard', route: '/admin/travel', width: 390, height: 844, mobile: true, mode: 'dark' },
        { name: 'desktop-light-tour-catalog', route: '/admin/travel/catalog', width: 1440, height: 1000, mobile: false, mode: 'light' },
        { name: 'desktop-dark-tour-editor-basics', route: editorRoute, width: 1280, height: 900, mobile: false, mode: 'dark' },
        { name: 'tablet-light-tour-editor-route-reduced-motion', route: editorRoute, width: 820, height: 1080, mobile: false, mode: 'light', reducedMotion: true, before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[1]?.click()`, expected: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[1]?.getAttribute('aria-selected') === 'true'` },
        { name: 'mobile-dark-tour-editor-basics', route: editorRoute, width: 390, height: 844, mobile: true, mode: 'dark' },
        { name: 'mobile-light-tour-catalog', route: '/admin/travel/catalog', width: 390, height: 844, mobile: true, mode: 'light' },
        { name: 'desktop-light-category-dialog', route: '/admin/travel/catalog/categories', width: 1440, height: 1000, mobile: false, mode: 'light', before: `document.querySelector('#travel-tour-category-manager .card-header button')?.click()`, expected: `Boolean(document.querySelector('#travel-tour-category-manager .modal.show[aria-modal="true"]'))` },
        { name: 'tablet-dark-destination-media', route: '/admin/travel/catalog/destinations', width: 820, height: 1080, mobile: false, mode: 'dark', before: `document.querySelector('#travel-destination-manager button[title="View destination"]')?.click()`, expected: `Boolean(document.querySelector('#travel-destination-manager .modal.show[aria-modal="true"] #travel-destination-detail-images'))` },
    ];
    if (qaPhase === 'k2d') captures.push(
        { name: 'desktop-light-tour-itinerary-day-modal', route: editorRoute, width: 1440, height: 1000, mobile: false, mode: 'light', before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[2]?.click()`, expected: `document.querySelector('[aria-label="Itinerary editor"]') !== null`, after: `document.querySelector('[aria-label="Itinerary editor"] .travel-child-toolbar button')?.click()`, afterExpected: `document.querySelector('.travel-child-modal #itinerary-day-title') !== null` },
        { name: 'tablet-dark-tour-experience-faq-modal', route: editorRoute, width: 820, height: 1080, mobile: false, mode: 'dark', before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[3]?.click()`, expected: `document.querySelector('[aria-label="Tour experience editor"]') !== null`, after: `document.querySelector('#experience-faq-heading')?.closest('.travel-child-toolbar')?.querySelector('button')?.click()`, afterExpected: `document.querySelector('.travel-child-modal #experience-faq-question') !== null` },
        { name: 'desktop-dark-tour-experience-item-modal', route: editorRoute, width: 1280, height: 900, mobile: false, mode: 'dark', before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[3]?.click()`, expected: `document.querySelector('[aria-label="Tour experience editor"]') !== null`, after: `document.querySelector('#experience-content-heading')?.closest('.travel-child-toolbar')?.querySelector('button')?.click()`, afterExpected: `document.querySelector('.travel-child-modal #experience-item-content') !== null` },
        { name: 'mobile-dark-tour-itinerary-activity-modal', route: editorRoute, width: 390, height: 844, mobile: true, mode: 'dark', before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[2]?.click()`, expected: `document.querySelector('[aria-label="Itinerary editor"]') !== null`, after: `[...document.querySelectorAll('[aria-label="Itinerary editor"] .travel-activity-list button')].find((button) => button.textContent.includes('Add activity'))?.click()`, afterExpected: `document.querySelector('.travel-child-modal #itinerary-activity-title') !== null` },
        { name: 'mobile-light-tour-experience-extra-modal', route: editorRoute, width: 390, height: 844, mobile: true, mode: 'light', before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[3]?.click()`, expected: `document.querySelector('[aria-label="Tour experience editor"]') !== null`, after: `document.querySelector('#experience-extra-heading')?.closest('.travel-child-toolbar')?.querySelector('button')?.click()`, afterExpected: `document.querySelector('.travel-child-modal #experience-extra-code') !== null` },
    );
    if (qaPhase === 'k2e') captures.push(
        { name: 'desktop-dark-tour-pricing', route: editorRoute, width: 1280, height: 900, mobile: false, mode: 'dark', before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[4]?.click()`, expected: `document.querySelector('#tour-base-pricing-heading') !== null` },
        { name: 'tablet-light-tour-media', route: editorRoute, width: 820, height: 1080, mobile: false, mode: 'light', before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[5]?.click()`, expected: `document.querySelector('#tour-gallery-heading') !== null`, scrollSelector: '#tour-gallery-heading' },
        { name: 'mobile-dark-tour-gallery-dialog', route: editorRoute, width: 390, height: 844, mobile: true, mode: 'dark', before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[5]?.click()`, expected: `document.querySelector('#tour-gallery-heading') !== null`, after: `document.querySelector('#tour-gallery-heading')?.closest('.travel-child-toolbar')?.querySelector('button')?.click()`, afterExpected: `document.querySelector('.travel-child-modal #tour-gallery-upload') !== null` },
        { name: 'tablet-dark-tour-publication', route: editorRoute, width: 820, height: 1080, mobile: false, mode: 'dark', before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[6]?.click()`, expected: `document.querySelector('#tour-readiness-heading') !== null` },
        { name: 'mobile-light-tour-publication', route: editorRoute, width: 390, height: 844, mobile: true, mode: 'light', before: `document.querySelectorAll('#travel-tour-editor [role="tab"]')[6]?.click()`, expected: `document.querySelector('#tour-publish-heading') !== null`, scrollSelector: '#tour-publish-heading' },
    );
    if (qaPhase === 'k3a') captures.push(
        { name: 'desktop-light-departures', route: '/admin/travel/departures', width: 1440, height: 1000, mobile: false, mode: 'light' },
        { name: 'desktop-dark-departure-form', route: '/admin/travel/departures', width: 1280, height: 900, mobile: false, mode: 'dark', before: `document.querySelector('.travel-departures .card-header button')?.click()`, expected: `document.querySelector('#departure-code') !== null` },
        { name: 'tablet-light-tour-departures-reduced-motion', route: `${editorRoute}?section=departures`, width: 820, height: 1080, mobile: false, mode: 'light', reducedMotion: true, expected: `document.querySelector('#travel-departures-title') !== null` },
        { name: 'tablet-dark-departure-team', route: '/admin/travel/departures', width: 820, height: 1080, mobile: false, mode: 'dark', before: `document.querySelector('.travel-departures button[title="Departure team"]')?.click()`, expected: `document.querySelector('#departure-staff-title') !== null` },
        { name: 'mobile-dark-departure-form', route: '/admin/travel/departures', width: 390, height: 844, mobile: true, mode: 'dark', before: `document.querySelector('.travel-departures .card-header button')?.click()`, expected: `document.querySelector('#departure-code') !== null` },
        { name: 'narrow-light-departures', route: '/admin/travel/departures', width: 320, height: 720, mobile: true, mode: 'light' },
    );
    for (const definition of captures) await capture(definition);

    const bookingDeskCaptures = [
        { name: 'desktop-light-booking-desk', width: 1440, height: 1000, mobile: false, mode: 'light' },
        { name: 'mobile-dark-booking-desk', width: 390, height: 844, mobile: true, mode: 'dark' },
    ];
    for (const definition of bookingDeskCaptures) await captureBookingDesk(definition);

    assert(runtimeErrors.length === 0, `Runtime errors: ${runtimeErrors.join(' | ')}`);
    assert(networkErrors.length === 0, `Network errors: ${networkErrors.join(' | ')}`);
    await writeFile(path.join(outputDirectory, 'diagnostics.json'), JSON.stringify({ siteUrl, diagnostics, runtimeErrors, networkErrors, externalWarnings, failures }, null, 2));
    if (failures.length > 0) throw new Error(failures.join('\n'));
    process.stdout.write(`TravelTours operations QA passed with ${captures.length + bookingDeskCaptures.length} captures.\n`);
} finally {
    client?.close();
    const browserExited = new Promise((resolve) => browser.once('exit', resolve));
    browser.kill();
    await Promise.race([browserExited, delay(3000)]);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => undefined);
}
