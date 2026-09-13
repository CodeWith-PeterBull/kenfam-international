import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'dev', 'commerce-demo-data-qa');
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
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-commerce-demo-data-qa-'));
const browser = spawn(browserPath, [
    '--headless=new',
    '--disable-gpu',
    '--disable-background-networking',
    '--disable-extensions',
    '--hide-scrollbars',
    '--no-proxy-server',
    '--proxy-bypass-list=<-loopback>',
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

    async function waitFor(expression, timeout = 9000) {
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
    const loginReady = await waitFor("document.querySelector('input[name=\"email\"]') && document.querySelector('input[name=\"password\"]')");
    if (!loginReady) {
        const pageState = await evaluate(`({ url: location.href, title: document.title, body: document.body?.innerText.slice(0, 500) })`);
        throw new Error(`Login form did not render: ${JSON.stringify(pageState)}`);
    }
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

    async function capture(name, width, height, mobile, mode, exerciseArchive = false) {
        await setViewport(width, height, mobile);
        await setTheme(mode);
        await navigate(`${siteUrl}/admin/commerce/demo-data?qa=${name}-${Date.now()}`);
        await delay(350);
        assert(await waitFor("Boolean(window.Livewire && document.querySelector('[data-commerce-demo-data]'))", 5000), `${name}: Livewire did not initialize`);

        if (exerciseArchive) {
            await evaluate(`document.querySelector('#archive-existing-products').click()`);
            assert(await waitFor("Boolean(document.querySelector('#confirm-archive-products'))", 5000), `${name}: archive acknowledgement did not render`);
        }

        await evaluate(`(async () => {
            const images = [...document.querySelectorAll('.commerce-demo-context__media img')];

            images.forEach((image) => { image.loading = 'eager'; });

            for (const image of images) {
                image.scrollIntoView({ block: 'center' });
                await new Promise((resolve) => setTimeout(resolve, 80));
            }

            await Promise.all(images.map((image) => {
                if (image.complete) {
                    return Promise.resolve();
                }

                return new Promise((resolve) => {
                    image.addEventListener('load', resolve, { once: true });
                    image.addEventListener('error', resolve, { once: true });
                });
            }));

            window.scrollTo({ top: 0, behavior: 'instant' });
        })()`, true);
        assert(
            await waitFor("[...document.querySelectorAll('.commerce-demo-context__media img')].every((image) => image.complete && image.naturalWidth > 0)", 10000),
            `${name}: context thumbnails did not finish loading`,
        );

        const snapshot = await evaluate(`(() => {
            const root = document.querySelector('[data-commerce-demo-data]');
            const cards = [...document.querySelectorAll('.commerce-demo-context')];
            const images = [...document.querySelectorAll('.commerce-demo-context__media img')];
            const radios = [...document.querySelectorAll('input[name="demo-context"]')];
            const duplicateIds = [...document.querySelectorAll('[id]')]
                .map((element) => element.id)
                .filter((id, index, ids) => ids.indexOf(id) !== index);
            const overflow = [...document.querySelectorAll('.commerce-demo-context__title, .commerce-demo-context__description, .commerce-demo-context__state')]
                .filter((element) => element.scrollWidth > element.clientWidth + 2);
            const mediaBoxes = cards.map((card) => card.querySelector('.commerce-demo-context__media')?.getBoundingClientRect());

            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                theme: document.documentElement.dataset.theme,
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                root: Boolean(root),
                cards: cards.length,
                selectedCards: cards.filter((card) => card.classList.contains('is-selected')).length,
                radios: radios.length,
                checkedRadios: radios.filter((radio) => radio.checked).length,
                images: images.length,
                loadedImages: images.filter((image) => image.complete && image.naturalWidth > 0).length,
                stableMedia: mediaBoxes.every((box) => box && box.width > 140 && box.height > 100),
                duplicateIds: duplicateIds.length,
                overflow: overflow.length,
                archiveWarning: Boolean(document.querySelector('.commerce-demo-data-warning')),
                archiveConfirmation: Boolean(document.querySelector('#confirm-archive-products')),
                submitLabel: document.querySelector('.commerce-demo-data-submit')?.textContent.trim(),
                loaderHidden: Boolean(document.querySelector('[data-page-loader]')?.hidden),
            };
        })()`);

        diagnostics.push(snapshot);
        assert(snapshot.path === '/admin/commerce/demo-data', `${name}: wrong route rendered`);
        assert(snapshot.theme === mode, `${name}: expected ${mode} theme`);
        assert(snapshot.viewportWidth === snapshot.documentWidth, `${name}: document overflows viewport`);
        assert(snapshot.root && snapshot.cards === 11, `${name}: eleven context cards were not rendered`);
        assert(snapshot.selectedCards === 1 && snapshot.radios === 11 && snapshot.checkedRadios === 1, `${name}: radio selection is not singular`);
        assert(snapshot.images === 11 && snapshot.loadedImages === 11 && snapshot.stableMedia, `${name}: context thumbnails are incomplete or unstable`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate IDs found`);
        assert(snapshot.overflow === 0, `${name}: context text overflows`);
        assert(snapshot.loaderHidden, `${name}: loader did not settle`);
        assert(snapshot.submitLabel?.includes('Seed selected context'), `${name}: submit action is not labeled`);
        if (exerciseArchive) {
            assert(snapshot.archiveWarning && snapshot.archiveConfirmation, `${name}: archive acknowledgement did not appear`);
        }

        const screenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: true,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    await capture('desktop-light', 1440, 1000, false, 'light', true);
    await capture('tablet-dark', 820, 1080, false, 'dark');
    await capture('mobile-light', 390, 844, true, 'light');

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
    process.stdout.write(`Commerce demo-data QA passed with ${diagnostics.length} captures.\n`);
} finally {
    client?.close();
    const browserExited = new Promise((resolve) => browser.once('exit', resolve));
    browser.kill();
    await Promise.race([browserExited, delay(3000)]);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => undefined);
}
