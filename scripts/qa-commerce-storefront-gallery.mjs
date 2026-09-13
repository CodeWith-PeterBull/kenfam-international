/**
 * Browser QA for the product gallery slider, full-screen lightbox, the nav
 * icon-button hover contrast fix, and the share/assurances divider.
 *
 * Prerequisites: built Vite assets, demo data, and a Laravel server at
 * AUREON_QA_URL (default http://127.0.0.1:8016). AUREON_QA_PRODUCT is a
 * published product slug with multiple gallery images (default aureon-air-14).
 */
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'dev', 'commerce-storefront-gallery-qa');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8016';
const productSlug = process.env.AUREON_QA_PRODUCT || 'aureon-air-14';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9478);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);
if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-gallery-qa-'));
const browser = spawn(browserPath, [
    '--headless=new', '--disable-gpu', '--disable-background-networking', '--disable-extensions',
    '--hide-scrollbars', '--no-first-run', `--remote-debugging-port=${debuggingPort}`,
    `--user-data-dir=${profileDirectory}`, 'about:blank',
], { stdio: 'ignore' });

const delay = (ms) => new Promise((r) => setTimeout(r, ms));
const failures = [];
const assert = (condition, message) => { if (!condition) failures.push(message); };
async function fetchJson(url, o) { const r = await fetch(url, o); if (!r.ok) throw new Error(`${r.status}`); return r.json(); }
async function waitForBrowser() { for (let i = 0; i < 60; i++) { try { return await fetchJson(`http://127.0.0.1:${debuggingPort}/json/version`); } catch { await delay(200); } } throw new Error('no browser'); }
class Cdp {
    constructor(u) { this.ws = new WebSocket(u); this.n = 1; this.p = new Map(); this.l = new Map(); }
    async connect() { await new Promise((res, rej) => { this.ws.addEventListener('open', res, { once: true }); this.ws.addEventListener('error', rej, { once: true }); }); this.ws.addEventListener('message', (e) => { const m = JSON.parse(e.data); if (m.id) { const p = this.p.get(m.id); if (!p) return; this.p.delete(m.id); m.error ? p.reject(new Error(m.error.message)) : p.resolve(m.result); return; } this.l.get(m.method)?.forEach((f) => f(m.params)); }); }
    send(method, params = {}) { const id = this.n++; return new Promise((res, rej) => { this.p.set(id, { resolve: res, reject: rej }); this.ws.send(JSON.stringify({ id, method, params })); }); }
    once(method, t = 45000) { return new Promise((res, rej) => { const l = (p) => { clearTimeout(x); this.l.get(method)?.delete(l); res(p); }; const x = setTimeout(() => { this.l.get(method)?.delete(l); rej(new Error('timeout ' + method)); }, t); if (!this.l.has(method)) this.l.set(method, new Set()); this.l.get(method).add(l); }); }
    close() { this.ws.close(); }
}

let client;
const runtimeErrors = [];
const networkErrors = [];
const diagnostics = {};
try {
    await waitForBrowser();
    const t = await fetchJson(`http://127.0.0.1:${debuggingPort}/json/new?about:blank`, { method: 'PUT' });
    client = new Cdp(t.webSocketDebuggerUrl);
    await client.connect();
    await Promise.all([client.send('Page.enable'), client.send('Runtime.enable'), client.send('Network.enable'), client.send('Log.enable'), client.send('DOM.enable'), client.send('CSS.enable')]);
    const listen = (method, h) => { if (!client.l.has(method)) client.l.set(method, new Set()); client.l.get(method).add(h); };
    listen('Runtime.exceptionThrown', (e) => runtimeErrors.push(e.exceptionDetails?.text || 'exception'));
    listen('Log.entryAdded', (e) => { if (e.entry.level === 'error') runtimeErrors.push(e.entry.text); });
    listen('Network.responseReceived', (e) => { if (e.response.status >= 400) networkErrors.push(`${e.response.status} ${e.response.url}`); });

    const evaluate = async (e, aw = false) => { const r = await client.send('Runtime.evaluate', { expression: e, awaitPromise: aw, returnByValue: true }); if (r.exceptionDetails) throw new Error(r.exceptionDetails.text); return r.result.value; };
    const dismissLoader = async () => {
        await evaluate('window.AureonPageLoader?.dismiss?.()');
        for (let i = 0; i < 40; i++) { if (await evaluate(`(() => { const l = document.querySelector('[data-page-loader]'); return !l || l.hidden === true; })()`)) break; await delay(100); }
    };
    const navigate = async (u) => { const l = client.once('Page.loadEventFired'); await client.send('Page.navigate', { url: u }); await l; await evaluate('document.fonts.ready', true); await dismissLoader(); await delay(700); };
    const setViewport = (w, h, m) => client.send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile: m });
    const shot = async (name) => { const s = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false }); await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(s.data, 'base64')); };
    // Force a CSS pseudo-state (e.g. hover) on the first element matching a selector.
    const forcePseudo = async (selector, classes) => {
        const { root: docRoot } = await client.send('DOM.getDocument', { depth: -1 });
        const { nodeId } = await client.send('DOM.querySelector', { nodeId: docRoot.nodeId, selector });
        if (!nodeId) throw new Error(`no node for ${selector}`);
        await client.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: classes });
        return nodeId;
    };

    const productUrl = `${siteUrl}/shop/products/${productSlug}`;

    // ---- Desktop light: gallery slider ----
    await setViewport(1440, 1600, false);
    await navigate(productUrl);

    const gallery = await evaluate(`(() => {
        const swiper = document.querySelector('.commerce-gallery-swiper');
        return {
            initialized: Boolean(swiper && swiper.classList.contains('swiper-initialized')),
            slides: document.querySelectorAll('.commerce-gallery-swiper .swiper-slide').length,
            hasPrev: Boolean(document.querySelector('[data-gallery-prev]')),
            hasNext: Boolean(document.querySelector('[data-gallery-next]')),
            bullets: document.querySelectorAll('.commerce-gallery-pagination .swiper-pagination-bullet').length,
            thumbs: document.querySelectorAll('[data-gallery-thumb]').length,
            hasExpand: Boolean(document.querySelector('[data-gallery-expand]')),
            overflow: document.documentElement.scrollWidth > innerWidth + 1,
        };
    })()`);
    diagnostics.gallery = gallery;
    assert(gallery.initialized, 'gallery Swiper did not initialize');
    assert(gallery.slides >= 2, `expected >= 2 slides, saw ${gallery.slides}`);
    assert(gallery.hasPrev && gallery.hasNext, 'slider arrows missing');
    assert(gallery.bullets >= 2, `pagination dots missing (${gallery.bullets})`);
    assert(gallery.thumbs >= 2, 'thumbnail tabs missing');
    assert(gallery.hasExpand, 'expand button missing');
    assert(!gallery.overflow, 'product page overflows horizontally');

    // Reveal the hover-only controls for the screenshot.
    await forcePseudo('.commerce-product-gallery__main', ['hover']);
    await delay(200);
    await shot('gallery-desktop-light');

    // ---- Thumbnail <-> slide sync ----
    await evaluate(`document.querySelectorAll('[data-gallery-thumb]')[1]?.click()`);
    await delay(500);
    const sync = await evaluate(`(() => {
        const active = document.querySelector('.commerce-gallery-swiper .swiper-slide-active');
        const slides = [...document.querySelectorAll('.commerce-gallery-swiper .swiper-slide')];
        const thumbActive = document.querySelectorAll('[data-gallery-thumb]')[1]?.classList.contains('is-active');
        return { activeIndex: slides.indexOf(active), thumbActive: Boolean(thumbActive) };
    })()`);
    diagnostics.sync = sync;
    assert(sync.activeIndex === 1, `thumb click did not move slider (active ${sync.activeIndex})`);
    assert(sync.thumbActive, 'clicked thumbnail not marked active');

    // ---- Nav icon-button hover contrast ----
    await forcePseudo('.commerce-primary-nav--right .commerce-icon-button', ['hover']);
    await delay(200);
    const hover = await evaluate(`(() => {
        const btn = document.querySelector('.commerce-primary-nav--right .commerce-icon-button');
        const svg = btn?.querySelector('svg');
        const cs = getComputedStyle(btn);
        const svgcs = svg ? getComputedStyle(svg) : null;
        const rootcs = getComputedStyle(document.documentElement);
        return {
            buttonColor: cs.color,
            buttonBackground: cs.backgroundColor,
            iconColor: svgcs?.color || null,
            iconStroke: svgcs?.stroke || null,
            onPrimary: rootcs.getPropertyValue('--theme-on-primary').trim(),
            primary: rootcs.getPropertyValue('--theme-primary').trim(),
        };
    })()`);
    diagnostics.navHover = hover;
    assert(hover.buttonColor !== hover.buttonBackground, `hovered icon colour equals background (${hover.buttonColor})`);
    await shot('nav-hover-default');

    // Repeat the hover check under a custom theme (mirrors the reported case).
    await evaluate(`(() => {
        const r = document.documentElement;
        r.dataset.customTheme = 'true';
        r.style.setProperty('--custom-primary', '#1f7a3d');
        r.style.setProperty('--custom-primary-contrast', '#ffffff');
    })()`);
    await delay(200);
    const hoverCustom = await evaluate(`(() => {
        const btn = document.querySelector('.commerce-primary-nav--right .commerce-icon-button');
        const cs = getComputedStyle(btn);
        const svg = btn?.querySelector('svg');
        return { buttonColor: cs.color, buttonBackground: cs.backgroundColor, iconColor: svg ? getComputedStyle(svg).color : null };
    })()`);
    diagnostics.navHoverCustom = hoverCustom;
    assert(hoverCustom.buttonColor !== hoverCustom.buttonBackground, `custom-theme hovered icon equals background (${hoverCustom.buttonColor})`);
    await evaluate(`(() => { const r = document.documentElement; r.dataset.customTheme = 'false'; r.style.removeProperty('--custom-primary'); r.style.removeProperty('--custom-primary-contrast'); })()`);

    // ---- Share / assurances divider ----
    const divider = await evaluate(`(() => {
        const el = document.querySelector('.commerce-product-assurances');
        const cs = el ? getComputedStyle(el) : null;
        return { borderTop: cs ? parseFloat(cs.borderTopWidth) : 0, paddingTop: cs ? parseFloat(cs.paddingTop) : 0 };
    })()`);
    diagnostics.divider = divider;
    assert(divider.borderTop >= 1, 'assurances divider border missing');
    assert(divider.paddingTop >= 8, 'assurances spacing missing');

    // ---- Lightbox: open, theme-aware, close ----
    await evaluate(`document.querySelector('[data-gallery-expand]')?.click()`);
    await delay(600);
    const lightboxOpen = await evaluate(`(() => {
        const lb = document.querySelector('[data-gallery-lightbox]');
        return {
            present: Boolean(lb),
            open: Boolean(lb && lb.classList.contains('is-open') && lb.hidden === false),
            slides: document.querySelectorAll('.commerce-gallery-lightbox__swiper .swiper-slide').length,
            hasClose: Boolean(document.querySelector('.commerce-gallery-lightbox__close')),
            hasNav: Boolean(document.querySelector('.commerce-gallery-lightbox__nav--next')),
            scrollLocked: document.body.classList.contains('commerce-scroll-lock'),
        };
    })()`);
    diagnostics.lightbox = lightboxOpen;
    assert(lightboxOpen.open, 'lightbox did not open');
    assert(lightboxOpen.slides >= 2, 'lightbox slides missing');
    assert(lightboxOpen.hasClose && lightboxOpen.hasNav, 'lightbox controls missing');
    assert(lightboxOpen.scrollLocked, 'body scroll not locked while lightbox open');

    // The image must fill the viewport space (scale up), not stay at its
    // natural size — measured against the dialog, contained (no crop).
    const lightboxFill = await evaluate(`(() => {
        const img = document.querySelector('.commerce-gallery-lightbox__swiper .swiper-slide-active img') || document.querySelector('.commerce-gallery-lightbox__swiper img');
        const dialog = document.querySelector('.commerce-gallery-lightbox__dialog');
        const r = img?.getBoundingClientRect();
        const dr = dialog?.getBoundingClientRect();
        return { imgW: Math.round(r?.width || 0), imgH: Math.round(r?.height || 0), dialogW: Math.round(dr?.width || 0), dialogH: Math.round(dr?.height || 0), objectFit: img ? getComputedStyle(img).objectFit : null };
    })()`);
    diagnostics.lightboxFill = lightboxFill;
    assert(lightboxFill.objectFit === 'contain', 'lightbox image is not contained');
    assert(lightboxFill.imgW >= lightboxFill.dialogW * 0.6 || lightboxFill.imgH >= lightboxFill.dialogH * 0.6, `lightbox image did not fill (${lightboxFill.imgW}x${lightboxFill.imgH} in ${lightboxFill.dialogW}x${lightboxFill.dialogH})`);
    await shot('lightbox-desktop-light');

    await evaluate(`document.documentElement.setAttribute('data-theme', 'dark')`);
    await delay(300);
    await shot('lightbox-desktop-dark');

    const lightboxClosed = await evaluate(`(() => {
        document.querySelector('.commerce-gallery-lightbox__close')?.click();
        return new Promise((resolve) => setTimeout(() => {
            const lb = document.querySelector('[data-gallery-lightbox]');
            resolve({ closed: Boolean(lb && lb.hidden === true && !lb.classList.contains('is-open')), unlocked: !document.body.classList.contains('commerce-scroll-lock') });
        }, 400));
    })()`, true);
    diagnostics.lightboxClosed = lightboxClosed;
    assert(lightboxClosed.closed, 'lightbox did not close');
    assert(lightboxClosed.unlocked, 'body scroll not restored after close');

    await evaluate(`document.documentElement.setAttribute('data-theme', 'light')`);

    // ---- Mobile ----
    await setViewport(390, 1500, true);
    await navigate(productUrl);
    const mobile = await evaluate(`(() => ({ overflow: document.documentElement.scrollWidth > innerWidth + 1, initialized: Boolean(document.querySelector('.commerce-gallery-swiper.swiper-initialized')) }))()`);
    diagnostics.mobile = mobile;
    assert(!mobile.overflow, 'product page overflows on mobile');
    assert(mobile.initialized, 'gallery did not initialize on mobile');
    await shot('gallery-mobile-light');

    const productChunk404 = networkErrors.filter((e) => e.includes('product-gallery'));
    assert(productChunk404.length === 0, `gallery chunk failed to load: ${productChunk404.join(', ')}`);
    assert(runtimeErrors.length === 0, `runtime errors: ${runtimeErrors.join(' | ')}`);
    assert(networkErrors.length === 0, `network errors: ${networkErrors.join(' | ')}`);

    await writeFile(path.join(outputDirectory, 'diagnostics.json'), JSON.stringify({ siteUrl, productUrl, diagnostics, runtimeErrors, networkErrors, failures }, null, 2));

    if (failures.length) throw new Error(failures.join('\n'));
    process.stdout.write('Commerce storefront gallery QA passed.\n');
} finally {
    client?.close();
    browser.kill();
    await delay(500);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => {});
}
