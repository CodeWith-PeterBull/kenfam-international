/** Browser QA for the Property Booking public discovery and checkout shell. */
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'PropertyBooking', 'qa', 'storefront');
const siteUrl = process.env.AUREON_QA_URL || 'http://localhost:8008';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9484);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-stays-qa-'));
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
const failures = [];
const diagnostics = [];
const runtimeErrors = [];
const networkErrors = [];
const assert = (condition, message) => { if (!condition) failures.push(message); };

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
            const listener = (parameters) => {
                clearTimeout(timer);
                this.listeners.get(method)?.delete(listener);
                resolve(parameters);
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

    const listen = (method, listener) => {
        if (!client.listeners.has(method)) client.listeners.set(method, new Set());
        client.listeners.get(method).add(listener);
    };
    listen('Runtime.exceptionThrown', (event) => runtimeErrors.push(event.exceptionDetails?.text || 'Runtime exception'));
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

    async function waitFor(expression, timeout = 15000) {
        const startedAt = Date.now();
        while (Date.now() - startedAt < timeout) {
            const value = await evaluate(expression);
            if (value) return value;
            await delay(150);
        }

        return null;
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

    async function settle() {
        await evaluate('document.fonts.ready', true);
        await evaluate('window.AureonPageLoader?.dismiss?.()');
        await waitFor(`(() => { const loader = document.querySelector('[data-page-loader]'); return !loader || loader.hidden; })()`);
        await delay(450);
    }

    async function navigate(url) {
        const loaded = client.once('Page.loadEventFired');
        await client.send('Page.navigate', { url });
        await loaded;
        await settle();
    }

    async function setTheme(mode) {
        await evaluate(`window.AureonTheme?.save?.({ ...window.AureonTheme.read(), mode: ${JSON.stringify(mode)} })`);
        await delay(180);
    }

    async function screenshot(name) {
        const capture = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(capture.data, 'base64'));
    }

    async function inspect(name, expectedPath, expectedTheme) {
        const snapshot = await evaluate(`(() => {
            const visible = (element) => Boolean(element && element.offsetParent !== null);
            const named = (element) => element.textContent.trim() || element.getAttribute('aria-label') || element.getAttribute('title');
            const controls = [...document.querySelectorAll('main input:not([type="hidden"]), main select, main textarea')].filter(visible);
            const buttons = [...document.querySelectorAll('button')].filter(visible);
            const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
            const images = [...document.querySelectorAll('main img')].filter(visible);
            const overflowingText = [...document.querySelectorAll('main h1,main h2,main h3,main p,main small,main strong,main span,main a')]
                .filter((element) => visible(element) && element.scrollWidth > element.clientWidth + 3 && getComputedStyle(element).whiteSpace !== 'nowrap');
            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                theme: document.documentElement.dataset.theme,
                title: document.querySelector('main h1')?.textContent.trim() || '',
                documentWidth: document.documentElement.scrollWidth,
                viewportWidth: innerWidth,
                mainWidth: document.querySelector('main')?.scrollWidth || 0,
                loaderHidden: !document.querySelector('[data-page-loader]') || document.querySelector('[data-page-loader]').hidden,
                footer: Boolean(document.querySelector('footer')),
                themeController: Boolean(document.querySelector('[data-theme-controller]')),
                duplicateIds: ids.filter((id, index) => ids.indexOf(id) !== index).length,
                unlabeledButtons: buttons.filter((button) => !named(button)).length,
                unlabeledControls: controls.filter((control) => !control.closest('label') && (!control.id || !document.querySelector('label[for="' + CSS.escape(control.id) + '"]'))).length,
                brokenImages: images.filter((image) => image.complete && image.naturalWidth === 0).length,
                overflowingText: overflowingText.length,
            };
        })()`);
        diagnostics.push(snapshot);
        assert(snapshot.path === expectedPath, `${name}: expected ${expectedPath}, saw ${snapshot.path}`);
        assert(snapshot.theme === expectedTheme, `${name}: expected ${expectedTheme} theme`);
        assert(snapshot.title, `${name}: page heading is missing`);
        assert(snapshot.documentWidth <= snapshot.viewportWidth + 1, `${name}: document overflows horizontally`);
        assert(snapshot.mainWidth <= snapshot.viewportWidth + 1, `${name}: main content overflows horizontally`);
        assert(snapshot.loaderHidden, `${name}: page loader did not settle`);
        assert(snapshot.footer && snapshot.themeController, `${name}: base storefront components are incomplete`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate IDs found`);
        assert(snapshot.unlabeledButtons === 0, `${name}: visible button lacks an accessible name`);
        assert(snapshot.unlabeledControls === 0, `${name}: visible form control lacks a label`);
        assert(snapshot.brokenImages === 0, `${name}: visible image failed to render`);
        assert(snapshot.overflowingText === 0, `${name}: visible text overflows its container`);

        return snapshot;
    }

    await setViewport(1440, 1000, false);
    await navigate(`${siteUrl}/stays`);
    await setTheme('light');
    await inspect('desktop-light-catalog', '/stays', 'light');
    const catalog = await evaluate(`(() => ({
        properties: document.querySelectorAll('.pb-stay-card').length,
        firstProperty: document.querySelector('.pb-stay-card__image')?.href || '',
        desktopHeader: getComputedStyle(document.querySelector('.pb-storefront-desktop')).display !== 'none',
        resultGrid: getComputedStyle(document.querySelector('.pb-stay-result-grid')).display,
    }))()`);
    diagnostics.push({ name: 'catalog-contract', ...catalog });
    assert(catalog.properties >= 1 && catalog.firstProperty, 'Catalog did not render a published seeded property');
    assert(catalog.desktopHeader, 'Desktop header variant is not active');
    assert(catalog.resultGrid === 'grid', 'Storefront stylesheet did not load');
    await screenshot('desktop-light-catalog');

    await navigate(catalog.firstProperty);
    await setTheme('dark');
    await inspect('desktop-dark-property', new URL(catalog.firstProperty).pathname, 'dark');
    assert(Boolean(await waitFor(`document.querySelector('[data-stay-gallery][data-gallery-ready="true"]')`)), 'Property gallery did not initialize');
    const property = await evaluate(`(() => ({
        images: document.querySelectorAll('[data-stay-gallery-stage] img').length,
        slides: document.querySelectorAll('[data-stay-gallery-stage] .swiper-slide').length,
        imageWidth: document.querySelector('[data-stay-gallery-stage] img')?.getBoundingClientRect().width || 0,
        unitUrl: document.querySelector('.pb-stay-unit-card h3 a')?.href || '',
        concreteCodeExposed: /DEMO-(ROOM|SUITE|UNIT)-/i.test(document.querySelector('main').innerText),
    }))()`);
    diagnostics.push({ name: 'property-gallery', ...property });
    assert(property.images >= 2 && property.slides >= 2, 'Property gallery lacks seeded photography');
    assert(property.imageWidth >= 500, 'Property photography canvas is not presentation-sized');
    assert(property.unitUrl, 'Property page has no public unit-type link');
    assert(!property.concreteCodeExposed, 'Concrete unit identity leaked into public output');
    await screenshot('desktop-dark-property');

    await evaluate(`document.querySelector('[data-stay-gallery-expand]')?.click()`);
    assert(Boolean(await waitFor(`document.querySelector('.pb-stay-lightbox.is-open:not([hidden])')`)), 'Property lightbox did not open');
    await delay(300);
    const lightbox = await evaluate(`(() => ({
        modal: document.querySelector('.pb-stay-lightbox__dialog')?.getAttribute('aria-modal'),
        imageWidth: document.querySelector('.pb-stay-lightbox .swiper-slide-active img')?.getBoundingClientRect().width || 0,
        imageHeight: document.querySelector('.pb-stay-lightbox .swiper-slide-active img')?.getBoundingClientRect().height || 0,
        scrollLocked: document.body.classList.contains('pb-stay-scroll-lock'),
        closeFocused: document.activeElement?.classList.contains('pb-stay-lightbox__close'),
    }))()`);
    diagnostics.push({ name: 'property-lightbox', ...lightbox });
    assert(lightbox.modal === 'true' && lightbox.scrollLocked && lightbox.closeFocused, 'Lightbox accessibility or scroll lock is incomplete');
    assert(lightbox.imageWidth >= 500 || lightbox.imageHeight >= 500, 'Lightbox image does not fill the viewing canvas');
    await screenshot('desktop-dark-property-lightbox');
    await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
    assert(Boolean(await waitFor(`document.querySelector('.pb-stay-lightbox')?.hidden === true`)), 'Escape did not close the lightbox');

    await setViewport(820, 1080, false);
    await navigate(property.unitUrl);
    await setTheme('light');
    await inspect('tablet-light-unit', new URL(property.unitUrl).pathname, 'light');
    assert(Boolean(await waitFor(`document.querySelector('[data-stay-gallery][data-gallery-ready="true"]')`)), 'Unit gallery did not initialize');
    const unit = await evaluate(`(() => ({
        tabletHeader: getComputedStyle(document.querySelector('.pb-storefront-compact')).display !== 'none',
        rates: document.querySelectorAll('.pb-stay-rate-list article').length,
        selectButton: Boolean(document.querySelector('.pb-stay-rate-list__price button')),
    }))()`);
    diagnostics.push({ name: 'unit-availability', ...unit });
    assert(unit.tabletHeader, 'Tablet header variant is not active');
    assert(unit.rates >= 1 && unit.selectButton, 'Unit page did not expose a current selectable rate');
    await screenshot('tablet-light-unit');

    await evaluate(`document.querySelector('.pb-stay-rate-list__price button')?.click()`);
    assert(Boolean(await waitFor(`location.pathname === '/stays/selection'`, 20000)), 'Selecting a rate did not reach review');
    await settle();
    await inspect('tablet-light-selection', '/stays/selection', 'light');
    await screenshot('tablet-light-selection');

    await setViewport(1440, 1100, false);
    await navigate(`${siteUrl}/stays/checkout`);
    await setTheme('light');
    await inspect('desktop-light-checkout', '/stays/checkout', 'light');
    const checkout = await evaluate(`(() => ({
        fields: document.querySelectorAll('.pb-stay-checkout-form input, .pb-stay-checkout-form textarea').length,
        identityType: Boolean(document.querySelector('#booking-identity-type')),
        identityNumber: Boolean(document.querySelector('#booking-identity-number')),
        summary: Boolean(document.querySelector('.pb-stay-checkout-summary')),
        submit: Boolean(document.querySelector('.pb-stay-checkout-summary button[type="submit"]')),
        error: document.querySelector('[role="alert"]')?.textContent.trim() || '',
    }))()`);
    diagnostics.push({ name: 'checkout-contract', ...checkout });
    assert(checkout.fields >= 10 && checkout.summary && checkout.submit && !checkout.error, 'Checkout did not preserve the selected stay or complete guest form');
    assert(checkout.identityType && checkout.identityNumber, 'Checkout identity controls are missing');
    await screenshot('desktop-light-checkout');

    await setViewport(390, 844, true);
    await navigate(`${siteUrl}/stays`);
    await setTheme('dark');
    await client.send('Emulation.setEmulatedMedia', { media: 'screen', features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await inspect('mobile-dark-catalog', '/stays', 'dark');
    await evaluate(`document.querySelector('.pb-storefront-mobile button[aria-label="Open navigation"]')?.click()`);
    assert(Boolean(await waitFor(`document.querySelector('#pbMobileMenu.show')`)), 'Mobile navigation did not open');
    const mobile = await evaluate(`(() => {
        const menu = document.querySelector('#pbMobileMenu');
        const close = menu?.querySelector('button[aria-label="Close navigation"]');
        const header = menu?.querySelector('.offcanvas-header');
        const closeBox = close?.getBoundingClientRect();
        const headerBox = header?.getBoundingClientRect();
        return {
            mobileHeader: getComputedStyle(document.querySelector('.pb-storefront-mobile')).display !== 'none',
            reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
            closeRightGap: headerBox && closeBox ? Math.round(headerBox.right - closeBox.right) : null,
            menuOverflow: menu ? menu.scrollWidth > menu.clientWidth + 1 : true,
        };
    })()`);
    diagnostics.push({ name: 'mobile-menu', ...mobile });
    assert(mobile.mobileHeader && mobile.reducedMotion, 'Mobile header or reduced-motion mode is not active');
    assert(mobile.closeRightGap !== null && mobile.closeRightGap <= 28, 'Mobile menu close control is not aligned to the far right');
    assert(!mobile.menuOverflow, 'Mobile navigation overflows horizontally');
    await screenshot('mobile-dark-navigation');

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
    process.stdout.write('Property Booking storefront QA passed with six viewport captures and one lightbox capture.\n');
} finally {
    client?.close();
    const browserExited = new Promise((resolve) => browser.once('exit', resolve));
    browser.kill();
    await Promise.race([browserExited, delay(3000)]);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => undefined);
}
