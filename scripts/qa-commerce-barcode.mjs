/**
 * Authenticated browser QA for the Commerce barcode label workspace and the
 * product-catalog barcode fields.
 *
 * Prerequisites: built Vite assets, demo data, and a Laravel server at
 * AUREON_QA_URL (default http://127.0.0.1:8014). AUREON_QA_PRODUCT is a
 * published product ULID used to preselect a label row.
 */
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'dev', 'commerce-barcode-qa');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8014';
const email = process.env.AUREON_QA_EMAIL || 'admin@aureon.test';
const password = process.env.AUREON_QA_PASSWORD || 'password';
const productUlid = process.env.AUREON_QA_PRODUCT || '';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9475);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);
if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-barcode-qa-'));
const browser = spawn(browserPath, [
    '--headless=new', '--disable-gpu', '--disable-background-networking', '--disable-extensions',
    '--hide-scrollbars', '--no-first-run', `--remote-debugging-port=${debuggingPort}`,
    `--user-data-dir=${profileDirectory}`, 'about:blank',
], { stdio: 'ignore' });

const delay = (ms) => new Promise((r) => setTimeout(r, ms));
const failures = [];
const assert = (condition, message) => { if (!condition) failures.push(message); };

async function fetchJson(url, options) {
    const response = await fetch(url, options);
    if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);
    return response.json();
}
async function waitForBrowser() {
    for (let i = 0; i < 60; i++) { try { return await fetchJson(`http://127.0.0.1:${debuggingPort}/json/version`); } catch { await delay(200); } }
    throw new Error('Browser debugging endpoint did not become ready');
}
class CdpClient {
    constructor(url) { this.ws = new WebSocket(url); this.nextId = 1; this.pending = new Map(); this.listeners = new Map(); }
    async connect() {
        await new Promise((resolve, reject) => { this.ws.addEventListener('open', resolve, { once: true }); this.ws.addEventListener('error', reject, { once: true }); });
        this.ws.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);
            if (message.id) { const p = this.pending.get(message.id); if (!p) return; this.pending.delete(message.id); message.error ? p.reject(new Error(`${p.method}: ${message.error.message}`)) : p.resolve(message.result); return; }
            this.listeners.get(message.method)?.forEach((l) => l(message.params));
        });
    }
    send(method, params = {}) { const id = this.nextId++; return new Promise((resolve, reject) => { this.pending.set(id, { method, resolve, reject }); this.ws.send(JSON.stringify({ id, method, params })); }); }
    once(method, timeout = 45000) { return new Promise((resolve, reject) => { const l = (p) => { clearTimeout(t); this.listeners.get(method)?.delete(l); resolve(p); }; const t = setTimeout(() => { this.listeners.get(method)?.delete(l); reject(new Error(`timeout ${method}`)); }, timeout); if (!this.listeners.has(method)) this.listeners.set(method, new Set()); this.listeners.get(method).add(l); }); }
    close() { this.ws.close(); }
}

let client;
const runtimeErrors = [];
const networkErrors = [];
try {
    await waitForBrowser();
    const target = await fetchJson(`http://127.0.0.1:${debuggingPort}/json/new?about:blank`, { method: 'PUT' });
    client = new CdpClient(target.webSocketDebuggerUrl);
    await client.connect();
    await Promise.all([client.send('Page.enable'), client.send('Runtime.enable'), client.send('Network.enable'), client.send('Log.enable')]);
    const listen = (method, handler) => { if (!client.listeners.has(method)) client.listeners.set(method, new Set()); client.listeners.get(method).add(handler); };
    listen('Runtime.exceptionThrown', (e) => runtimeErrors.push(e.exceptionDetails?.text || 'exception'));
    listen('Log.entryAdded', (e) => { if (e.entry.level === 'error') runtimeErrors.push(e.entry.text); });
    listen('Network.responseReceived', (e) => { if (e.response.status >= 400) networkErrors.push(`${e.response.status} ${e.response.url}`); });

    const evaluate = async (expr, awaitPromise = false) => { const r = await client.send('Runtime.evaluate', { expression: expr, awaitPromise, returnByValue: true }); if (r.exceptionDetails) throw new Error(r.exceptionDetails.text); return r.result.value; };
    const navigate = async (url) => { const l = client.once('Page.loadEventFired'); await client.send('Page.navigate', { url }); await l; await evaluate('document.fonts.ready', true); await delay(500); };
    const setTheme = async (mode) => { await evaluate(`(() => { const s = JSON.parse(localStorage.getItem('laravel-aureon-dashboard-settings')||'{}'); s.mode='${mode}'; localStorage.setItem('laravel-aureon-dashboard-settings', JSON.stringify(s)); })()`); };
    const setViewport = (w, h, mobile) => client.send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile });
    const shot = async (name) => { const s = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false }); await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(s.data, 'base64')); };

    // Login.
    await setViewport(1440, 1000, false);
    await navigate(`${siteUrl}/login`);
    const loggedIn = client.once('Page.loadEventFired');
    await evaluate(`(() => { document.querySelector('input[name="email"]').value = ${JSON.stringify(email)}; document.querySelector('input[name="password"]').value = ${JSON.stringify(password)}; document.querySelector('form').requestSubmit(); })()`);
    await loggedIn;

    // Barcode workspace with a preselected product row (desktop light).
    const barcodeUrl = `${siteUrl}/admin/commerce/catalog/barcodes${productUlid ? `?product=${productUlid}` : ''}`;
    await navigate(barcodeUrl);
    const light = await evaluate(`(() => ({
        hasSheet: Boolean(document.querySelector('[data-barcode-sheet]')),
        previewImages: [...document.querySelectorAll('[data-barcode-sheet] img')].filter((i) => (i.currentSrc || i.src).startsWith('data:image/png')).length,
        previewComplete: [...document.querySelectorAll('[data-barcode-sheet] img')].every((i) => i.complete && i.naturalWidth > 0),
        rows: document.querySelectorAll('[data-barcode-sheet] tbody tr').length,
        generateButton: Boolean([...document.querySelectorAll('button')].find((b) => /label sheet/i.test(b.textContent))),
        overflow: document.documentElement.scrollWidth > innerWidth + 1,
    }))()`);
    assert(light.hasSheet, 'barcode sheet component did not render');
    assert(!light.overflow, 'barcode page overflows horizontally');
    assert(light.generateButton, 'download label sheet button missing');
    if (productUlid) {
        assert(light.rows >= 1, 'preselected product row did not appear');
        assert(light.previewImages >= 1, 'no server-generated barcode preview image rendered');
        assert(light.previewComplete, 'a barcode preview image failed to load');
    }
    await shot('barcode-workspace-desktop-light');

    // Dark theme.
    await setTheme('dark');
    await navigate(barcodeUrl);
    const dark = await evaluate(`document.documentElement.dataset.bsTheme`);
    assert(dark === 'dark', 'dark theme did not apply');
    await shot('barcode-workspace-desktop-dark');

    // Mobile.
    await setViewport(390, 844, true);
    await navigate(barcodeUrl);
    const mobile = await evaluate(`(() => ({ overflow: document.documentElement.scrollWidth > innerWidth + 1 }))()`);
    assert(!mobile.overflow, 'barcode page overflows on mobile');
    await shot('barcode-workspace-mobile-dark');

    // Catalog page toolbar + form barcode fields (desktop light).
    await setTheme('light');
    await setViewport(1440, 1000, false);
    await navigate(`${siteUrl}/admin/commerce/catalog`);
    await evaluate(`document.querySelector('button[wire\\\\:click="openCreate"]')?.click()`);
    await delay(700);
    const form = await evaluate(`(() => ({
        internal: Boolean(document.querySelector('#product-barcode')),
        manufacturer: Boolean(document.querySelector('#product-manufacturer-barcode')),
        generate: Boolean([...document.querySelectorAll('button')].find((b) => b.querySelector('.ti-refresh'))),
        printButton: Boolean([...document.querySelectorAll('a')].find((a) => /print barcodes/i.test(a.textContent))),
    }))()`);
    assert(form.internal && form.manufacturer, 'internal or manufacturer barcode field missing on the product form');
    assert(form.generate, 'internal-barcode generate button missing');
    assert(form.printButton, 'catalog print-barcodes toolbar button missing');
    await shot('catalog-product-form-barcodes');

    assert(runtimeErrors.length === 0, `runtime errors: ${runtimeErrors.join(' | ')}`);
    assert(networkErrors.length === 0, `network errors: ${networkErrors.join(' | ')}`);
    await writeFile(path.join(outputDirectory, 'diagnostics.json'), JSON.stringify({ siteUrl, light, form, dark, runtimeErrors, networkErrors, failures }, null, 2));

    if (failures.length) throw new Error(failures.join('\n'));
    process.stdout.write(`Commerce barcode QA passed.\n`);
} finally {
    client?.close();
    browser.kill();
    await delay(500);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => {});
}
