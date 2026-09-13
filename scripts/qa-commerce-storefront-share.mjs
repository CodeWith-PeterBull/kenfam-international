/**
 * Browser QA for the storefront product share group, WhatsApp ordering, and SEO.
 *
 * Prerequisites: built Vite assets, demo data, and a Laravel server at
 * AUREON_QA_URL (default http://127.0.0.1:8015). AUREON_QA_PRODUCT is a
 * published product slug (default aureon-air-14). The storefront is public,
 * so no authentication is required.
 */
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'dev', 'commerce-storefront-share-qa');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8015';
const productSlug = process.env.AUREON_QA_PRODUCT || 'aureon-air-14';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9476);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);
if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-share-qa-'));
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
try {
    await waitForBrowser();
    const t = await fetchJson(`http://127.0.0.1:${debuggingPort}/json/new?about:blank`, { method: 'PUT' });
    client = new Cdp(t.webSocketDebuggerUrl);
    await client.connect();
    await Promise.all([client.send('Page.enable'), client.send('Runtime.enable'), client.send('Network.enable'), client.send('Log.enable')]);
    await client.send('Browser.grantPermissions', { origin: siteUrl, permissions: ['clipboardReadWrite', 'clipboardSanitizedWrite'] }).catch(() => {});
    const listen = (method, h) => { if (!client.l.has(method)) client.l.set(method, new Set()); client.l.get(method).add(h); };
    listen('Runtime.exceptionThrown', (e) => runtimeErrors.push(e.exceptionDetails?.text || 'exception'));
    listen('Log.entryAdded', (e) => { if (e.entry.level === 'error') runtimeErrors.push(e.entry.text); });
    listen('Network.responseReceived', (e) => { if (e.response.status >= 400) networkErrors.push(`${e.response.status} ${e.response.url}`); });

    const evaluate = async (e, aw = false) => { const r = await client.send('Runtime.evaluate', { expression: e, awaitPromise: aw, returnByValue: true }); if (r.exceptionDetails) throw new Error(r.exceptionDetails.text); return r.result.value; };
    const dismissLoader = async () => {
        await evaluate('window.AureonPageLoader?.dismiss?.()');
        for (let i = 0; i < 40; i++) {
            const hidden = await evaluate(`(() => { const l = document.querySelector('[data-page-loader]'); return !l || l.hidden === true; })()`);
            if (hidden) break;
            await delay(100);
        }
    };
    const navigate = async (u) => { const l = client.once('Page.loadEventFired'); await client.send('Page.navigate', { url: u }); await l; await evaluate('document.fonts.ready', true); await dismissLoader(); await delay(600); };
    const setViewport = (w, h, m) => client.send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile: m });
    const shot = async (name) => { const s = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false }); await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(s.data, 'base64')); };

    const productUrl = `${siteUrl}/shop/products/${productSlug}`;

    // Desktop light.
    await setViewport(1440, 1600, false);
    await navigate(productUrl);
    const light = await evaluate(`(() => {
        const jsonLd = [...document.querySelectorAll('script[type="application/ld+json"]')].map((s) => { try { return JSON.parse(s.textContent); } catch { return null; } }).filter(Boolean);
        return {
            shareButtons: document.querySelectorAll('.commerce-share__buttons .commerce-share__btn').length,
            brandSvgs: document.querySelectorAll('.commerce-share__btn svg').length,
            labels: document.querySelectorAll('.commerce-share__label').length,
            whatsappButton: Boolean([...document.querySelectorAll('a.commerce-button--whatsapp')].length),
            whatsappHref: document.querySelector('a.commerce-button--whatsapp')?.getAttribute('href') || null,
            ogType: document.querySelector('meta[property="og:type"]')?.content,
            twitterCard: document.querySelector('meta[name="twitter:card"]')?.content,
            hasProductLd: jsonLd.some((n) => n['@type'] === 'Product'),
            hasOrgLd: jsonLd.some((n) => n['@type'] === 'Organization'),
            productPrice: jsonLd.find((n) => n['@type'] === 'Product')?.offers?.price || null,
            overflow: document.documentElement.scrollWidth > innerWidth + 1,
        };
    })()`);
    assert(light.shareButtons >= 5, `expected the share button group, saw ${light.shareButtons}`);
    assert(light.brandSvgs >= 5, 'brand SVG icons did not render');
    assert(light.labels >= 5, 'share hover labels missing');
    assert(light.whatsappButton, 'Buy via WhatsApp button missing');
    assert((light.whatsappHref || '').startsWith('https://wa.me/'), 'WhatsApp button href is not a wa.me link');
    assert(light.ogType === 'product', `og:type is ${light.ogType}`);
    assert(light.twitterCard === 'summary_large_image', 'twitter card missing');
    assert(light.hasProductLd, 'Product JSON-LD missing');
    assert(light.hasOrgLd, 'Organization JSON-LD missing');
    assert(!light.overflow, 'product page overflows horizontally');
    await shot('product-share-desktop-light');

    // Copy button -> toast. Bring the page to front so the clipboard write is permitted.
    await client.send('Page.bringToFront');
    await evaluate(`(() => { window.focus(); document.querySelector('.commerce-share__btn--copy')?.click(); })()`);
    await delay(500);
    const toast = await evaluate(`(() => { const t = document.querySelector('[data-commerce-toast]'); return { present: Boolean(t), visible: t?.classList.contains('visible'), text: t?.textContent || '' }; })()`);
    assert(toast.present && toast.visible, 'copy toast did not appear');
    assert(/copied/i.test(toast.text), `copy did not succeed: "${toast.text}"`);
    await shot('product-share-copy-toast');

    // Dark theme.
    await evaluate(`document.documentElement.setAttribute('data-theme', 'dark')`);
    await delay(300);
    await shot('product-share-desktop-dark');

    // Mobile.
    await setViewport(390, 1400, true);
    await navigate(productUrl);
    const mobile = await evaluate(`(() => ({ overflow: document.documentElement.scrollWidth > innerWidth + 1, shareButtons: document.querySelectorAll('.commerce-share__btn').length }))()`);
    assert(!mobile.overflow, 'product page overflows on mobile');
    assert(mobile.shareButtons >= 5, 'share group missing on mobile');
    await shot('product-share-mobile-light');

    assert(runtimeErrors.length === 0, `runtime errors: ${runtimeErrors.join(' | ')}`);
    assert(networkErrors.length === 0, `network errors: ${networkErrors.join(' | ')}`);
    await writeFile(path.join(outputDirectory, 'diagnostics.json'), JSON.stringify({ siteUrl, productUrl, light, toast, mobile, runtimeErrors, networkErrors, failures }, null, 2));

    if (failures.length) throw new Error(failures.join('\n'));
    process.stdout.write('Commerce storefront share QA passed.\n');
} finally {
    client?.close();
    browser.kill();
    await delay(500);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => {});
}
