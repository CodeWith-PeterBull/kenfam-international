/** Responsive browser QA for the public Aureon CMS homepage. */
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'AureonHomepage', 'qa');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8009';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9491);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-home-qa-'));
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

    once(method, timeout = 30000) {
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
    await Promise.all([client.send('Page.enable'), client.send('Runtime.enable'), client.send('Network.enable')]);

    const evaluate = async (expression, awaitPromise = false) => {
        const response = await client.send('Runtime.evaluate', { expression, awaitPromise, returnByValue: true });
        if (response.exceptionDetails) throw new Error(response.exceptionDetails.text || 'Evaluation failed');
        return response.result.value;
    };

    const waitFor = async (expression, timeout = 15000) => {
        const startedAt = Date.now();
        while (Date.now() - startedAt < timeout) {
            const value = await evaluate(expression);
            if (value) return value;
            await delay(150);
        }
        return null;
    };

    const setViewport = async (width, height, mobile) => {
        await client.send('Emulation.setDeviceMetricsOverride', {
            width, height, deviceScaleFactor: 1, mobile, screenWidth: width, screenHeight: height,
        });
        await client.send('Emulation.setTouchEmulationEnabled', { enabled: mobile, maxTouchPoints: mobile ? 5 : 1 });
    };

    const navigate = async () => {
        const loaded = client.once('Page.loadEventFired');
        await client.send('Page.navigate', { url: `${siteUrl}/` });
        await loaded;
        await evaluate('document.fonts.ready', true);
        await evaluate('window.AureonPageLoader?.dismiss?.()');
        await waitFor(`!document.querySelector('[data-page-loader]') || document.querySelector('[data-page-loader]').hidden`);
        await delay(250);
    };

    const setTheme = async (mode) => {
        await evaluate(`window.AureonTheme.save({ ...window.AureonTheme.read(), mode: ${JSON.stringify(mode)} })`);
        await delay(150);
    };

    const capture = async (name) => {
        const result = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(result.data, 'base64'));
    };

    await setViewport(1440, 900, false);
    const loaderPageReady = client.once('Page.loadEventFired');
    await client.send('Page.navigate', { url: `${siteUrl}/?loader-qa=${Date.now()}` });
    await loaderPageReady;
    await delay(100);
    const loaderFirstPaint = await evaluate(`(() => {
        const loader = document.querySelector('[data-page-loader]');
        const content = loader?.querySelector('.aureon-loader__content');
        const image = loader?.querySelector('img');
        const loaderStyle = loader ? getComputedStyle(loader) : null;
        const loaderBox = loader?.getBoundingClientRect();
        const contentBox = content?.getBoundingClientRect();
        return {
            present: Boolean(loader),
            criticalStyles: Boolean(document.querySelector('style[data-aureon-loader-critical]')),
            state: document.documentElement.dataset.pageLoaderState,
            position: loaderStyle?.position,
            display: loaderStyle?.display,
            visibility: loaderStyle?.visibility,
            opacity: loaderStyle?.opacity,
            background: loaderStyle?.backgroundColor,
            coversViewport: loaderBox?.width === innerWidth && loaderBox?.height === innerHeight,
            centered: Math.abs((contentBox?.left + contentBox?.width / 2) - innerWidth / 2) < 2
                && Math.abs((contentBox?.top + contentBox?.height / 2) - innerHeight / 2) < 2,
            logoWidth: image?.getBoundingClientRect().width,
        };
    })()`);
    diagnostics.push({ name: 'loader-first-paint', ...loaderFirstPaint });
    if (!loaderFirstPaint.present || !loaderFirstPaint.criticalStyles) failures.push('Homepage critical loader contract is missing');
    if (loaderFirstPaint.state !== 'visible' || loaderFirstPaint.position !== 'fixed' || loaderFirstPaint.display !== 'grid') failures.push('Homepage loader was not active at first paint');
    if (loaderFirstPaint.visibility !== 'visible' || loaderFirstPaint.opacity !== '1') failures.push('Homepage loader was visually hidden at first paint');
    if (!loaderFirstPaint.coversViewport || !loaderFirstPaint.centered || loaderFirstPaint.logoWidth !== 62) failures.push('Homepage loader was not correctly framed at first paint');
    if (loaderFirstPaint.background === 'rgba(0, 0, 0, 0)') failures.push('Homepage loader background was transparent at first paint');
    await capture('loader-light-first-paint');
    await waitFor(`document.querySelector('[data-page-loader]')?.hidden`, 10000);

    await setTheme('dark');
    const darkLoaderPageReady = client.once('Page.loadEventFired');
    await client.send('Page.navigate', { url: `${siteUrl}/?loader-dark-qa=${Date.now()}` });
    await darkLoaderPageReady;
    await delay(100);
    const darkLoaderFirstPaint = await evaluate(`(() => {
        const loader = document.querySelector('[data-page-loader]');
        const style = loader ? getComputedStyle(loader) : null;
        return {
            theme: document.documentElement.dataset.theme,
            state: document.documentElement.dataset.pageLoaderState,
            visibility: style?.visibility,
            opacity: style?.opacity,
            background: style?.backgroundColor,
        };
    })()`);
    diagnostics.push({ name: 'loader-dark-first-paint', ...darkLoaderFirstPaint });
    if (darkLoaderFirstPaint.theme !== 'dark' || darkLoaderFirstPaint.state !== 'visible') failures.push('Homepage dark loader did not retain the persisted theme');
    if (darkLoaderFirstPaint.visibility !== 'visible' || darkLoaderFirstPaint.opacity !== '1') failures.push('Homepage dark loader was visually hidden at first paint');
    if (darkLoaderFirstPaint.background === loaderFirstPaint.background || darkLoaderFirstPaint.background === 'rgba(0, 0, 0, 0)') failures.push('Homepage dark loader did not use an opaque dark surface');
    await capture('loader-dark-first-paint');
    await waitFor(`document.querySelector('[data-page-loader]')?.hidden`, 10000);

    const inspect = async (name, expectedWidth, expectedTheme) => {
        const result = await evaluate(`(() => {
            const visible = (element) => Boolean(element && element.getClientRects().length);
            const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
            const images = [...document.images].filter(visible);
            return {
                name: ${JSON.stringify(name)},
                theme: document.documentElement.dataset.theme,
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                duplicateIds: ids.filter((id, index) => ids.indexOf(id) !== index).length,
                brokenImages: images.filter((image) => image.complete && image.naturalWidth === 0).length,
                loaderHidden: !document.querySelector('[data-page-loader]') || document.querySelector('[data-page-loader]').hidden,
                homeTitle: document.querySelector('h1')?.textContent.trim(),
                hashedStylesheet: (document.querySelector('link[href*="/build/css/aureon-home-"]')?.href ?? '').includes('.min.css'),
                hashedScript: (document.querySelector('script[src*="/build/js/aureon-home-"]')?.src ?? '').includes('.js'),
            };
        })()`);
        diagnostics.push(result);
        if (result.viewportWidth !== expectedWidth) failures.push(`${name}: expected ${expectedWidth}px viewport, received ${result.viewportWidth}px`);
        if (result.documentWidth > result.viewportWidth + 1) failures.push(`${name}: horizontal overflow ${result.documentWidth}px > ${result.viewportWidth}px`);
        if (result.theme !== expectedTheme) failures.push(`${name}: expected ${expectedTheme} theme`);
        if (result.duplicateIds !== 0) failures.push(`${name}: duplicate IDs detected`);
        if (result.brokenImages !== 0) failures.push(`${name}: ${result.brokenImages} broken images`);
        if (!result.loaderHidden) failures.push(`${name}: loader remained visible`);
        if (result.homeTitle !== 'Aureon CMS') failures.push(`${name}: homepage title was not rendered`);
        if (!result.hashedStylesheet || !result.hashedScript) failures.push(`${name}: homepage Vite entries are not content hashed`);
    };

    for (const scenario of [
        { name: 'desktop-light', width: 1440, height: 1000, mobile: false, theme: 'light' },
        { name: 'laptop-dark-modules', width: 1024, height: 900, mobile: false, theme: 'dark', target: '#modules' },
        { name: 'mobile-light', width: 390, height: 844, mobile: true, theme: 'light' },
        { name: 'mobile-dark-modules', width: 390, height: 844, mobile: true, theme: 'dark', target: '#modules' },
    ]) {
        await setViewport(scenario.width, scenario.height, scenario.mobile);
        await navigate();
        await setTheme(scenario.theme);
        if (scenario.target) {
            await evaluate(`document.querySelector(${JSON.stringify(scenario.target)}).scrollIntoView()`);
            await delay(250);
        }
        await inspect(scenario.name, scenario.width, scenario.theme);
        await capture(scenario.name);
    }

    await setViewport(1440, 900, false);
    await navigate();
    const galleryCounts = await evaluate(`[...document.querySelectorAll('[data-module-gallery]')].map((gallery) => gallery.querySelectorAll('[data-gallery-option]').length)`);
    if (galleryCounts.some((count) => count < 10)) failures.push('Every module gallery must expose at least ten views');
    await evaluate(`document.querySelector('#commerce [data-gallery-option][data-gallery-src*="commerce-pos.webp"]').click()`);
    const galleryChanged = await waitFor(`document.querySelector('#commerce [data-gallery-stage]').src.includes('commerce-pos.webp') && document.querySelector('#commerce [data-gallery-role]').textContent.trim() === 'Cashier'`);
    if (!galleryChanged) failures.push('Commerce gallery did not switch to the point-of-sale screenshot');
    await evaluate(`document.querySelector('#commerce .home-module-gallery').scrollIntoView({ block: 'center' })`);
    await delay(250);
    await capture('desktop-light-gallery-pos');

    await evaluate(`document.querySelector('#commerce [data-gallery-expand]').click()`);
    const lightboxOpened = await waitFor(`document.querySelector('[data-gallery-lightbox]').classList.contains('show') && document.querySelector('[data-lightbox-title]').textContent.trim() === 'Point of sale'`);
    if (!lightboxOpened) failures.push('Full-screen module gallery did not open on the selected screenshot');
    await evaluate(`document.querySelector('[data-gallery-lightbox]').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))`);
    const lightboxAdvanced = await waitFor(`document.querySelector('[data-lightbox-title]').textContent.trim() === 'My sales history' && document.querySelector('#commerce [data-gallery-stage]').src.includes('commerce-cashier-sales.webp')`);
    if (!lightboxAdvanced) failures.push('Full-screen gallery keyboard navigation did not remain synchronized with the module gallery');
    const desktopLightboxDetails = await evaluate(`(() => ({
        title: document.querySelector('[data-lightbox-title]').textContent.trim(),
        position: document.querySelector('[data-lightbox-position]').textContent.trim(),
        sourceSynchronized: document.querySelector('[data-lightbox-image]').src === document.querySelector('#commerce [data-gallery-stage]').src,
        viewportCovered: document.querySelector('[data-gallery-lightbox] .modal-content').getBoundingClientRect().height >= innerHeight,
    }))()`);
    diagnostics.push({ name: 'desktop-gallery-lightbox', ...desktopLightboxDetails });
    if (!desktopLightboxDetails.sourceSynchronized || !desktopLightboxDetails.viewportCovered) failures.push('Desktop full-screen gallery presentation is incomplete');
    await delay(250);
    await capture('desktop-light-gallery-fullscreen');
    await evaluate(`window.bootstrap.Modal.getInstance(document.querySelector('[data-gallery-lightbox]')).hide()`);
    await waitFor(`!document.querySelector('[data-gallery-lightbox]').classList.contains('show')`);

    await setViewport(390, 844, true);
    await navigate();
    await setTheme('light');
    await evaluate(`document.querySelector('#property-booking [data-gallery-expand]').click()`);
    const mobileLightbox = await waitFor(`document.querySelector('[data-gallery-lightbox]').classList.contains('show') && document.querySelector('[data-lightbox-title]').textContent.trim() === 'Stay discovery'`);
    if (!mobileLightbox) failures.push('Mobile full-screen module gallery did not open');
    const mobileLightboxDetails = await evaluate(`(() => ({
        title: document.querySelector('[data-lightbox-title]').textContent.trim(),
        viewportWidth: document.querySelector('[data-gallery-lightbox] .modal-content').getBoundingClientRect().width,
        documentWidth: document.documentElement.scrollWidth,
    }))()`);
    diagnostics.push({ name: 'mobile-gallery-lightbox', ...mobileLightboxDetails });
    if (mobileLightboxDetails.viewportWidth !== 390 || mobileLightboxDetails.documentWidth > 391) failures.push('Mobile full-screen gallery overflowed the viewport');
    await delay(250);
    await capture('mobile-light-gallery-fullscreen');
    await evaluate(`window.bootstrap.Modal.getInstance(document.querySelector('[data-gallery-lightbox]')).hide()`);
    await waitFor(`!document.querySelector('[data-gallery-lightbox]').classList.contains('show')`);
    await evaluate(`document.querySelector('[data-bs-target="#homeMobileMenu"]').click()`);
    await waitFor(`document.querySelector('#homeMobileMenu').classList.contains('show')`);
    const menuAlignment = await evaluate(`(() => {
        const header = document.querySelector('.home-mobile-menu__header').getBoundingClientRect();
        const close = document.querySelector('.home-mobile-menu__header [data-bs-dismiss]').getBoundingClientRect();
        return { gap: Math.round(header.right - close.right), closeCenter: Math.round(close.left + close.width / 2), headerCenter: Math.round(header.width / 2) };
    })()`);
    diagnostics.push({ name: 'mobile-menu-alignment', ...menuAlignment });
    if (menuAlignment.closeCenter <= menuAlignment.headerCenter || menuAlignment.gap > 30) failures.push('Mobile menu close control is not aligned to the far right');
    await capture('mobile-light-menu');

    await evaluate(`document.querySelector('#homeMobileMenu [data-aureon-contact-trigger]').click()`);
    const contactOpened = await waitFor(`document.querySelector('[data-aureon-contact-card]').classList.contains('show') && !document.querySelector('#homeMobileMenu').classList.contains('show')`);
    const contactDetails = await evaluate(`(() => ({
        visible: document.querySelector('[data-aureon-contact-card]').classList.contains('show'),
        whatsappLinks: document.querySelectorAll('[data-aureon-contact-card] a[href^="https://wa.me/"]').length,
        emailLinks: document.querySelectorAll('[data-aureon-contact-card] a[href^="mailto:"]').length,
        phoneLinks: document.querySelectorAll('[data-aureon-contact-card] a[href^="tel:"]').length,
    }))()`);
    diagnostics.push({ name: 'mobile-contact-card', ...contactDetails });
    if (!contactOpened || !contactDetails.visible) failures.push('Mobile quote action did not hand off from the menu to the contact card');
    if (contactDetails.whatsappLinks !== 2 || contactDetails.emailLinks !== 2 || contactDetails.phoneLinks !== 2) failures.push('Contact card does not expose both WhatsApp, email, and telephone channels');
    await delay(450);
    await capture('mobile-light-contact-card');
    await setTheme('dark');
    await capture('mobile-dark-contact-card');

    await writeFile(path.join(outputDirectory, 'diagnostics.json'), `${JSON.stringify({ failures, diagnostics }, null, 2)}\n`);
    if (failures.length > 0) throw new Error(`Homepage QA failed:\n- ${failures.join('\n- ')}`);
    process.stdout.write(`Homepage QA passed (${diagnostics.length} checks).\n`);
} finally {
    client?.close();
    const browserExited = new Promise((resolve) => browser.once('exit', resolve));
    browser.kill();
    await Promise.race([browserExited, delay(2000)]);

    for (let attempt = 0; attempt < 5; attempt += 1) {
        try {
            await rm(profileDirectory, { recursive: true, force: true });
            break;
        } catch (error) {
            if (attempt === 4 || !['EBUSY', 'EPERM'].includes(error.code)) throw error;
            await delay(250);
        }
    }
}
