/**
 * Guest browser QA for the Commerce catalog, cart, checkout, and signed orders.
 *
 * Prerequisites: `npm.cmd run build`, the optional Commerce demo seeder, and a
 * Laravel server at AUREON_QA_URL (default http://127.0.0.1:8012).
 */
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = process.env.AUREON_QA_OUTPUT
    ? path.resolve(root, process.env.AUREON_QA_OUTPUT)
    : path.join(root, '.docs', 'dev', 'commerce-qa');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8012';
const confirmationUrl = process.env.AUREON_QA_CONFIRMATION_URL || '';
const trackingUrl = process.env.AUREON_QA_TRACKING_URL || '';
const searchTerm = process.env.AUREON_QA_SEARCH || 'Arc ANC';
const expectedSearchProduct = process.env.AUREON_QA_SEARCH_PRODUCT || 'Arc ANC Headphones';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9460);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-commerce-qa-'));
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

function assert(condition, message) {
    if (!condition) failures.push(message);
}

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

    async function waitFor(expression, timeout = 5000) {
        const startedAt = Date.now();
        while (Date.now() - startedAt < timeout) {
            const value = await evaluate(expression);
            if (value) return value;
            await delay(120);
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

    async function setTheme(mode) {
        await evaluate(`(() => {
            const current = window.AureonTheme.read();
            window.AureonTheme.save({ ...current, mode: ${JSON.stringify(mode)} });
        })()`);
        await delay(100);
    }

    async function capture(name, expectedHeader, { requireImages = true } = {}) {
        await evaluate(`Promise.all([...document.querySelectorAll('main img')].map((image) => new Promise((resolve) => {
            image.loading = 'eager';
            if (image.complete) return resolve(true);
            image.addEventListener('load', () => resolve(true), { once: true });
            image.addEventListener('error', () => resolve(false), { once: true });
            setTimeout(() => resolve(false), 5000);
        })))`, true);
        await evaluate('window.scrollTo(0, 0)');

        const snapshot = await evaluate(`(() => {
            const visible = (selector) => {
                const element = document.querySelector(selector);
                return Boolean(element && getComputedStyle(element).display !== 'none' && element.getBoundingClientRect().height > 0);
            };
            const images = [...document.querySelectorAll('main img')];
            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                theme: document.documentElement.dataset.theme,
                loaderHidden: Boolean(document.querySelector('[data-page-loader]')?.hidden),
                desktopHeader: visible('.commerce-header-desktop'),
                tabletHeader: visible('.commerce-header-tablet'),
                mobileHeader: visible('.commerce-header-mobile'),
                livewirePresent: Boolean(document.querySelector('[wire\\\\:id]')),
                themeControllerPresent: Boolean(document.querySelector('[data-theme-controller]')),
                productCards: document.querySelectorAll('.commerce-product-card').length,
                images: images.length,
                loadedImages: images.filter((image) => image.complete && image.naturalWidth > 0).length,
                unlabeledButtons: [...document.querySelectorAll('button')].filter((button) => !button.textContent.trim() && !button.getAttribute('aria-label') && !button.getAttribute('title')).length,
                unlabeledFields: [...document.querySelectorAll('input, select, textarea')].filter((field) => {
                    if (field.getAttribute('aria-label') || field.getAttribute('aria-labelledby')) return false;
                    if (field.closest('label')) return false;
                    return !field.id || !document.querySelector('label[for="' + CSS.escape(field.id) + '"]');
                }).length,
                duplicateIds: [...document.querySelectorAll('[id]')].map((element) => element.id).filter((id, index, ids) => ids.indexOf(id) !== index).length,
            };
        })()`);
        diagnostics.push(snapshot);
        assert(snapshot.viewportWidth === snapshot.documentWidth, `${name}: document overflows viewport`);
        assert(snapshot.loaderHidden, `${name}: loader did not settle`);
        assert(snapshot.themeControllerPresent, `${name}: theme controller missing`);
        if (requireImages) assert(snapshot.images > 0 && snapshot.loadedImages === snapshot.images, `${name}: one or more product images failed`);
        assert(snapshot.unlabeledButtons === 0, `${name}: found buttons without accessible names`);
        assert(snapshot.unlabeledFields === 0, `${name}: found form controls without labels`);
        assert(snapshot.duplicateIds === 0, `${name}: found duplicate element IDs`);
        assert(snapshot[`${expectedHeader}Header`] === true, `${name}: ${expectedHeader} header is not visible`);

        const screenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    await setViewport(1440, 1000, false);
    await navigate(`${siteUrl}/shop`);
    await setTheme('light');
    await capture('catalog-desktop', 'desktop');
    const firstProductUrl = await evaluate("document.querySelector('.commerce-product-card h3 a')?.href");
    assert(Boolean(firstProductUrl), 'catalog: first product detail URL missing');

    const filtered = await evaluate(`new Promise(async (resolve) => {
        const root = document.querySelector('#storefront-catalog');
        const component = window.Livewire.find(root.getAttribute('wire:id'));
        await component.set('search', ${JSON.stringify(searchTerm)});
        setTimeout(() => resolve({
            count: document.querySelectorAll('.commerce-product-card').length,
            text: document.querySelector('#storefront-catalog').innerText,
            url: location.search,
        }), 300);
    })`, true);
    diagnostics.push({ name: 'catalog-livewire-search', ...filtered });
    assert(filtered.count === 1, 'catalog: Livewire search did not narrow to one product');
    assert(filtered.text.includes(expectedSearchProduct), 'catalog: Livewire search result missing');
    assert(new URLSearchParams(filtered.url).get('q') === searchTerm, 'catalog: search state was not synchronized to the URL');

    const added = await evaluate(`new Promise(async (resolve) => {
        const root = document.querySelector('.commerce-product-card .commerce-add-to-cart');
        const component = root ? window.Livewire.find(root.getAttribute('wire:id')) : null;
        if (!component) return resolve({ added: false, count: 0 });
        await component.call('add');
        setTimeout(() => resolve({
            added: true,
            count: Number(document.querySelector('.commerce-cart-indicator > span')?.textContent || 0),
            feedback: root.textContent,
        }), 350);
    })`, true);
    added.count = Number(await waitFor("Number(document.querySelector('.commerce-cart-indicator > span')?.textContent || 0)") || 0);
    diagnostics.push({ name: 'catalog-add-to-cart', ...added });
    assert(added.added && added.count > 0, 'catalog: add-to-cart did not update the session indicator');

    await navigate(`${siteUrl}/shop/cart`);
    await capture('cart-desktop', 'desktop');
    const cart = await evaluate(`(() => ({
        lines: document.querySelectorAll('.commerce-cart-line').length,
        hasSummary: Boolean(document.querySelector('.commerce-order-summary')),
        checkoutUrl: document.querySelector('a[href*="/shop/checkout"]')?.href,
    }))()`);
    diagnostics.push({ name: 'cart-state', ...cart });
    assert(cart.lines > 0, 'cart: selected product line is missing');
    assert(cart.hasSummary && Boolean(cart.checkoutUrl), 'cart: checkout summary or action is missing');

    await navigate(`${siteUrl}/shop/checkout`);
    await capture('checkout-desktop', 'desktop', { requireImages: false });
    const deliveryComponent = await evaluate(`(() => {
        const root = document.querySelector('.commerce-checkout-layout');
        const component = root ? window.Livewire.find(root.getAttribute('wire:id')) : null;
        if (!component) return false;
        component.set('form.fulfillmentType', 'delivery');
        return true;
    })()`);
    const addressVisible = Boolean(await waitFor("Boolean(document.querySelector('#checkout-address-1'))"));
    const delivery = await evaluate(`(() => ({
        component: ${JSON.stringify(deliveryComponent)},
        addressVisible: ${JSON.stringify(addressVisible)},
        paymentChoices: document.querySelectorAll('.commerce-choice-grid--three input[type="radio"]').length,
        malformedChoices: [...document.querySelectorAll('.commerce-choice')].filter((choice) =>
            choice.querySelectorAll('.commerce-choice__copy').length !== 1
            || choice.querySelectorAll('.commerce-choice__copy > strong').length !== 1
            || choice.querySelectorAll('.commerce-choice__copy > small').length !== 1
        ).length,
    }))()`);
    diagnostics.push({ name: 'checkout-delivery', ...delivery });
    assert(delivery.component && delivery.addressVisible, 'checkout: delivery address fields did not render');
    assert(delivery.paymentChoices >= 3, 'checkout: configured payment choices are incomplete');
    assert(delivery.malformedChoices === 0, 'checkout: a Livewire morph duplicated or malformed option content');

    await setViewport(1080, 1080, false);
    await setTheme('dark');
    await capture('checkout-laptop-dark', 'tablet', { requireImages: false });

    await setViewport(390, 844, true);
    await setTheme('dark');
    await capture('checkout-mobile-dark', 'mobile', { requireImages: false });

    await setViewport(820, 1080, false);
    await navigate(`${siteUrl}/shop`);
    await capture('catalog-tablet', 'tablet');

    await setViewport(390, 844, true);
    await navigate(`${siteUrl}/shop`);
    await capture('catalog-mobile', 'mobile');
    const mobileMenu = await evaluate(`new Promise((resolve) => {
        document.querySelector('[data-bs-target="#commerceMobileMenu"]').click();
        setTimeout(() => {
            const panel = document.querySelector('#commerceMobileMenu');
            const close = panel.querySelector('[data-bs-dismiss="offcanvas"]');
            const rect = close.getBoundingClientRect();
            resolve({ open: panel.classList.contains('show'), closeRight: Math.round(rect.right), viewport: innerWidth });
        }, 500);
    })`, true);
    assert(mobileMenu.open, 'mobile catalog: navigation offcanvas did not open');
    assert(mobileMenu.viewport - mobileMenu.closeRight <= 28, 'mobile catalog: close button is not aligned to the far right');

    await navigate(firstProductUrl);
    await setViewport(1440, 1000, false);
    await setTheme('light');
    await capture('product-detail-desktop', 'desktop');
    const gallery = await evaluate(`(() => {
        const main = document.querySelector('[data-product-gallery-main]');
        const thumbnails = [...document.querySelectorAll('[data-product-gallery-thumb]')];
        const before = main?.src;
        if (thumbnails[1]) thumbnails[1].click();
        return { thumbnails: thumbnails.length, before, after: main?.src };
    })()`);
    if (gallery.thumbnails > 1) assert(gallery.before !== gallery.after, 'product detail: gallery selection did not update the main image');

    await setViewport(390, 844, true);
    await setTheme('dark');
    await capture('product-detail-mobile-dark', 'mobile');

    if (confirmationUrl) {
        await setViewport(1440, 1000, false);
        await setTheme('light');
        await navigate(confirmationUrl);
        await capture('order-confirmation-desktop', 'desktop', { requireImages: false });
        const confirmation = await evaluate(`(() => ({
            success: Boolean(document.querySelector('.commerce-order-hero--success')),
            details: Boolean(document.querySelector('.commerce-public-order-grid')),
        }))()`);
        assert(confirmation.success && confirmation.details, 'confirmation: signed order detail surface is incomplete');
    }

    if (trackingUrl) {
        await setViewport(390, 844, true);
        await setTheme('dark');
        await navigate(trackingUrl);
        await capture('order-tracking-mobile-dark', 'mobile', { requireImages: false });
        const tracking = await evaluate(`(() => ({
            steps: document.querySelectorAll('.commerce-tracking-steps li').length,
            details: Boolean(document.querySelector('.commerce-public-order-grid')),
        }))()`);
        assert(tracking.steps === 5 && tracking.details, 'tracking: fulfillment progress or order details are incomplete');
    }

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
    process.stdout.write(`Commerce QA passed with ${diagnostics.length} captures.\n`);
} finally {
    client?.close();
    const browserExited = new Promise((resolve) => browser.once('exit', resolve));
    browser.kill();
    await Promise.race([browserExited, delay(3000)]);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => undefined);
}
