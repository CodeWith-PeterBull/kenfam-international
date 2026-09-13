/**
 * Authenticated browser QA for Property Booking Point of Booking surfaces.
 *
 * Prerequisites: built assets, demo property data, one open reception shift,
 * and a paid POB receipt path supplied through AUREON_QA_RECEIPT_PATH.
 */
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'PropertyBooking', 'qa', 'pob');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8012';
const email = process.env.AUREON_QA_EMAIL || 'admin@aureon.test';
const password = process.env.AUREON_QA_PASSWORD || 'password';
const receiptPath = process.env.AUREON_QA_RECEIPT_PATH || '';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9492);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');
if (!receiptPath.startsWith('/pob/receipts/')) throw new Error('AUREON_QA_RECEIPT_PATH must identify a POB receipt');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-pob-qa-'));
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
    await client.send('Page.addScriptToEvaluateOnNewDocument', {
        source: `(() => {
            window.__aureonPobPrintEvents = [];
            window.addEventListener('property-booking:receipt-print', (event) => {
                window.__aureonPobPrintEvents.push(event.detail);
                event.preventDefault();
            });
        })();`,
    });

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
        await evaluate('window.AureonPageLoader?.dismiss?.()');
        await waitFor(`(() => { const loader = document.querySelector('[data-page-loader]'); return !loader || loader.hidden; })()`);
        await delay(250);
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

    async function screenshot(name) {
        const capture = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(capture.data, 'base64'));
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
    assert(!(await evaluate('location.pathname')).startsWith('/login'), 'Login did not leave the authentication page');

    async function captureTerminal(name, width, height, mobile, mode, reducedMotion = false) {
        await setViewport(width, height, mobile);
        await client.send('Emulation.setEmulatedMedia', {
            media: 'screen',
            features: [{ name: 'prefers-reduced-motion', value: reducedMotion ? 'reduce' : 'no-preference' }],
        });
        await setTheme(mode);
        await navigate(`${siteUrl}/pob?qa=${name}-${Date.now()}`);
        assert(Boolean(await waitFor("document.querySelector('.pob-terminal-grid')")), `${name}: terminal did not render an open shift`);
        await evaluate("document.querySelector('.pob-rate-card')?.click()");
        assert(Boolean(await waitFor("document.querySelector('.pob-selection')")), `${name}: rate selection did not hydrate`);
        await evaluate(`Promise.all([...document.querySelectorAll('.pob-rate-card img')].map((image) => new Promise((resolve) => {
            image.loading = 'eager';
            if (image.complete) return resolve(true);
            image.addEventListener('load', () => resolve(true), { once: true });
            image.addEventListener('error', () => resolve(false), { once: true });
            setTimeout(() => resolve(false), 5000);
        })))`, true);
        await evaluate('window.scrollTo(0, 0)');

        const snapshot = await evaluate(`(() => {
            const visible = (element) => Boolean(element && element.offsetParent !== null);
            const named = (element) => element.textContent.trim() || element.getAttribute('aria-label') || element.getAttribute('title');
            const labeled = (field) => field.closest('label') || field.getAttribute('aria-label') || (field.id && document.querySelector('label[for="' + CSS.escape(field.id) + '"]'));
            const images = [...document.querySelectorAll('.pob-rate-card img')];
            const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
            const importantText = [...document.querySelectorAll('.pob-pane h2,.pob-pane h3,.pob-rate-card strong,.pob-rate-card__price,.pob-context strong,.pob-button')].filter(visible);
            const outside = [...document.querySelectorAll('.pob-workspace button,.pob-workspace input,.pob-workspace select,.pob-workspace textarea')]
                .filter(visible)
                .filter((element) => { const box = element.getBoundingClientRect(); return box.left < -1 || box.right > innerWidth + 1; });
            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                theme: document.documentElement.dataset.theme,
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                workspaceWidth: document.querySelector('.pob-workspace')?.scrollWidth || 0,
                loaderHidden: !document.querySelector('[data-page-loader]') || document.querySelector('[data-page-loader]').hidden,
                topbar: visible(document.querySelector('.pob-topbar')),
                context: visible(document.querySelector('.pob-context')),
                terminal: getComputedStyle(document.querySelector('.pob-terminal-grid')).display === 'grid',
                panes: document.querySelectorAll('.pob-pane').length,
                rates: document.querySelectorAll('.pob-rate-card').length,
                selection: visible(document.querySelector('.pob-selection')),
                nowAction: visible([...document.querySelectorAll('button')].find((button) => button.getAttribute('wire:click') === 'useCurrentArrival')),
                images: images.length,
                loadedImages: images.filter((image) => image.complete && image.naturalWidth > 0).length,
                unlabeledButtons: [...document.querySelectorAll('button')].filter((button) => visible(button) && !named(button)).length,
                unlabeledFields: [...document.querySelectorAll('input:not([type="hidden"]),select,textarea')].filter((field) => visible(field) && !labeled(field)).length,
                duplicateIds: ids.filter((id, index) => ids.indexOf(id) !== index).length,
                outsideControls: outside.length,
                textOverflow: importantText.filter((element) => {
                    const style = getComputedStyle(element);
                    return element.scrollWidth > element.clientWidth + 3 && style.overflowX === 'visible' && style.whiteSpace !== 'normal';
                }).length,
                reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
            };
        })()`);
        diagnostics.push(snapshot);
        assert(snapshot.path === '/pob', `${name}: wrong route rendered`);
        assert(snapshot.theme === mode, `${name}: expected ${mode} theme`);
        assert(snapshot.viewportWidth === snapshot.documentWidth, `${name}: document overflows viewport`);
        assert(snapshot.workspaceWidth <= snapshot.viewportWidth + 1, `${name}: workspace overflows viewport`);
        assert(snapshot.loaderHidden, `${name}: page loader did not settle`);
        assert(snapshot.topbar && snapshot.context && snapshot.terminal, `${name}: terminal structure is incomplete`);
        assert(snapshot.panes === 3 && snapshot.rates >= 1 && snapshot.selection, `${name}: booking workflow is incomplete`);
        assert(snapshot.nowAction, `${name}: current-arrival action is unavailable`);
        assert(snapshot.images === snapshot.loadedImages, `${name}: accommodation image failed to load`);
        assert(snapshot.unlabeledButtons === 0, `${name}: visible button lacks an accessible name`);
        assert(snapshot.unlabeledFields === 0, `${name}: visible field lacks an accessible label`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate IDs found`);
        assert(snapshot.outsideControls === 0, `${name}: control is outside the viewport`);
        assert(snapshot.textOverflow === 0, `${name}: operational text overflows its control`);
        assert(snapshot.reducedMotion === reducedMotion, `${name}: reduced-motion preference mismatch`);
        await screenshot(name);
    }

    async function captureAdministration(name, route, width, height, mobile, mode, expectedCards) {
        await setViewport(width, height, mobile);
        await client.send('Emulation.setEmulatedMedia', { media: 'screen', features: [] });
        await setTheme(mode);
        await navigate(`${siteUrl}${route}?qa=${name}-${Date.now()}`);

        const snapshot = await evaluate(`(() => {
            const cards = [...document.querySelectorAll('.pb-stat-card')];
            const boxes = cards.map((card) => card.getBoundingClientRect());
            const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                theme: document.documentElement.dataset.theme,
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                loaderHidden: !document.querySelector('[data-page-loader]') || document.querySelector('[data-page-loader]').hidden,
                cards: cards.length,
                grid: getComputedStyle(document.querySelector('.pb-stat-grid')).display === 'grid',
                painted: cards.every((card) => getComputedStyle(card).backgroundColor !== 'rgba(0, 0, 0, 0)'),
                complete: cards.every((card) => card.querySelector('.pb-stat-card__icon') && card.querySelector('strong') && card.querySelector('small')),
                stableDimensions: boxes.every((box) => box.width >= 180 && box.height >= 75),
                outsideCards: boxes.filter((box) => box.left < -1 || box.right > innerWidth + 1).length,
                duplicateIds: ids.filter((id, index) => ids.indexOf(id) !== index).length,
                textOverflow: cards.flatMap((card) => [...card.querySelectorAll('strong,small')]).filter((element) => element.scrollWidth > element.clientWidth + 3).length,
            };
        })()`);
        diagnostics.push(snapshot);
        assert(snapshot.path === route, `${name}: wrong route rendered`);
        assert(snapshot.theme === mode, `${name}: expected ${mode} theme`);
        assert(snapshot.viewportWidth === snapshot.documentWidth, `${name}: document overflows viewport`);
        assert(snapshot.loaderHidden, `${name}: page loader did not settle`);
        assert(snapshot.cards === expectedCards && snapshot.grid, `${name}: statistic grid is incomplete`);
        assert(snapshot.painted && snapshot.complete && snapshot.stableDimensions, `${name}: statistic card presentation is incomplete`);
        assert(snapshot.outsideCards === 0 && snapshot.textOverflow === 0, `${name}: statistic card is clipped or overflowing`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate IDs found`);
        await screenshot(name);
    }

    async function captureRegisterDialog() {
        await setViewport(1080, 900, false);
        await setTheme('dark');
        await navigate(`${siteUrl}/admin/accommodation/pob/registers?qa=printer-dialog-${Date.now()}`);
        await evaluate(` [...document.querySelectorAll('button')]
            .find((button) => button.getAttribute('wire:click') === 'openCreate')
            ?.click()`);
        assert(Boolean(await waitFor("document.querySelector('.modal.show')")), 'Register printer dialog did not open');
        const dialog = await evaluate(`(() => {
            const modal = document.querySelector('.modal-dialog');
            const box = modal?.getBoundingClientRect();
            const fields = [...document.querySelectorAll('#register-driver,#register-mode,#register-width,#register-printer')];
            return {
                fields: fields.length,
                values: fields.map((field) => field.value),
                unlabeled: fields.filter((field) => !document.querySelector('label[for="' + field.id + '"]')).length,
                insideViewport: Boolean(box && box.left >= 0 && box.right <= innerWidth && box.top >= 0 && box.bottom <= innerHeight),
                overflow: Boolean(modal && modal.scrollWidth > modal.clientWidth + 1),
            };
        })()`);
        diagnostics.push({ name: 'register-printer-dialog-dark', ...dialog });
        assert(dialog.fields === 4 && dialog.unlabeled === 0, 'Register printer controls are incomplete or unlabeled');
        assert(dialog.values[0] === 'browser' && ['manual', 'auto_prompt'].includes(dialog.values[1]) && ['58', '80'].includes(dialog.values[2]), 'Register printer values are invalid');
        assert(dialog.insideViewport && !dialog.overflow, 'Register printer dialog is clipped or overflowing');
        await screenshot('register-printer-dialog-dark');
    }

    async function captureReceipt() {
        await setViewport(820, 1080, false);
        await client.send('Emulation.setEmulatedMedia', { media: 'screen', features: [] });
        await setTheme('light');
        await navigate(`${siteUrl}${receiptPath}`);
        const receipt = await evaluate(`(() => ({
            path: location.pathname,
            visible: Boolean(document.querySelector('.pob-receipt')?.getBoundingClientRect().height),
            rows: document.querySelectorAll('.pob-receipt tbody tr').length,
            printButton: Boolean(document.querySelector('[data-pob-print]')),
            pdfLink: Boolean(document.querySelector('a[href$="/pdf"]')),
            overflow: document.documentElement.scrollWidth > innerWidth,
            instruction: JSON.parse(document.querySelector('[data-pob-print-instruction]')?.textContent || '{}'),
            paperWidth: Number(document.querySelector('[data-pob-receipt]')?.dataset.pobPaperWidth),
        }))()`);
        diagnostics.push({ name: 'receipt-screen-light', ...receipt });
        assert(receipt.path === receiptPath && receipt.visible && receipt.rows > 0, 'Receipt did not render its booking rows');
        assert(receipt.printButton && receipt.pdfLink && !receipt.overflow, 'Receipt actions or responsive layout are incomplete');
        assert([58, 80].includes(receipt.paperWidth), 'Receipt paper width is invalid');
        assert(receipt.instruction.strategy === 'browser-dialog' && receipt.instruction.supportsSilentPrinting === false, 'Receipt browser driver contract is invalid');
        await screenshot('receipt-screen-light');

        await evaluate("document.querySelector('[data-pob-print]')?.click()");
        const manualEvent = await waitFor('window.__aureonPobPrintEvents[0]', 3000);
        assert(manualEvent?.reason === 'manual', 'Manual receipt action did not dispatch the print adapter event');
        diagnostics.push({ name: 'receipt-manual-print-event', event: manualEvent || null });

        await setTheme('dark');
        await navigate(`${siteUrl}${receiptPath}`);
        const darkScreen = await evaluate(`(() => ({
            headingColor: getComputedStyle(document.querySelector('.pob-receipt h1')).color,
            paymentHeadingColor: getComputedStyle(document.querySelector('.pob-receipt__payments h2')).color,
            cellBackground: getComputedStyle(document.querySelector('.pob-receipt tbody td')).backgroundColor,
            cellColor: getComputedStyle(document.querySelector('.pob-receipt tbody td')).color,
        }))()`);
        diagnostics.push({ name: 'receipt-screen-dark', ...darkScreen });
        assert(
            darkScreen.headingColor === 'rgb(17, 17, 17)'
                && darkScreen.paymentHeadingColor === 'rgb(17, 17, 17)',
            'Dark screen theme leaked into receipt headings',
        );
        assert(
            darkScreen.cellBackground === 'rgb(255, 255, 255)'
                && darkScreen.cellColor === 'rgb(17, 17, 17)',
            'Dark screen theme leaked into the receipt line-item table',
        );
        await screenshot('receipt-screen-dark');
        await client.send('Emulation.setEmulatedMedia', { media: 'print' });
        const printState = await evaluate(`(() => ({
            documentTheme: document.documentElement.dataset.theme,
            colorScheme: getComputedStyle(document.documentElement).colorScheme,
            pageBackground: getComputedStyle(document.documentElement).backgroundColor,
            bodyBackground: getComputedStyle(document.body).backgroundColor,
            receiptBackground: getComputedStyle(document.querySelector('.pob-receipt')).backgroundColor,
            tableBackground: getComputedStyle(document.querySelector('.pob-receipt table')).backgroundColor,
            bodySectionBackground: getComputedStyle(document.querySelector('.pob-receipt tbody')).backgroundColor,
            rowBackground: getComputedStyle(document.querySelector('.pob-receipt tbody tr')).backgroundColor,
            cellBackground: getComputedStyle(document.querySelector('.pob-receipt tbody td')).backgroundColor,
            cellColor: getComputedStyle(document.querySelector('.pob-receipt tbody td')).color,
            topbarHidden: getComputedStyle(document.querySelector('.pob-topbar')).display === 'none',
            actionsHidden: getComputedStyle(document.querySelector('.pob-receipt-actions')).display === 'none',
            receiptWidth: document.querySelector('.pob-receipt').getBoundingClientRect().width,
            paperWidth: Number(document.querySelector('[data-pob-receipt]').dataset.pobPaperWidth),
        }))()`);
        diagnostics.push({ name: 'receipt-print-dark', ...printState });
        assert(printState.documentTheme === 'dark', 'Dark print precondition was not applied');
        assert(printState.colorScheme === 'light', 'Print output did not force a light paper color scheme');
        assert(printState.topbarHidden && printState.actionsHidden, 'Print media did not hide non-receipt controls');
        assert([
            printState.pageBackground,
            printState.bodyBackground,
            printState.receiptBackground,
            printState.tableBackground,
            printState.bodySectionBackground,
            printState.rowBackground,
            printState.cellBackground,
        ].every((color) => color === 'rgb(255, 255, 255)'), 'Dark theme leaked into receipt print surfaces');
        assert(printState.cellColor === 'rgb(17, 17, 17)', 'Receipt line text lacks paper contrast');
        const expectedWidth = (printState.paperWidth - 8) * (96 / 25.4);
        assert(Math.abs(printState.receiptWidth - expectedWidth) <= 3, 'Receipt width does not match its roll profile');
        await screenshot('receipt-print-dark');
        await client.send('Emulation.setEmulatedMedia', { media: 'screen', features: [] });
    }

    await captureTerminal('terminal-desktop-light', 1440, 1000, false, 'light');
    await captureTerminal('terminal-laptop-dark', 1080, 900, false, 'dark');
    await captureTerminal('terminal-tablet-light', 820, 1080, false, 'light');
    await captureTerminal('terminal-mobile-dark-reduced-motion', 390, 844, true, 'dark', true);
    await captureAdministration('register-admin-desktop-light', '/admin/accommodation/pob/registers', 1440, 1000, false, 'light', 3);
    await captureAdministration('shift-admin-desktop-dark', '/admin/accommodation/pob/shifts', 1440, 1000, false, 'dark', 4);
    await captureAdministration('register-admin-mobile-dark', '/admin/accommodation/pob/registers', 390, 844, true, 'dark', 3);
    await captureAdministration('shift-admin-mobile-light', '/admin/accommodation/pob/shifts', 390, 844, true, 'light', 4);
    await captureRegisterDialog();
    await captureReceipt();

    assert(runtimeErrors.length === 0, `Runtime errors: ${runtimeErrors.join(' | ')}`);
    assert(networkErrors.length === 0, `Network errors: ${networkErrors.join(' | ')}`);
    await writeFile(path.join(outputDirectory, 'diagnostics.json'), JSON.stringify({
        siteUrl,
        receiptPath,
        diagnostics,
        runtimeErrors,
        networkErrors,
        failures,
    }, null, 2));
    if (failures.length > 0) throw new Error(failures.join('\n'));
    process.stdout.write(`Property Booking POB QA passed with ${diagnostics.length} diagnostics and 12 captures.\n`);
} finally {
    client?.close();
    const browserExited = new Promise((resolve) => browser.once('exit', resolve));
    browser.kill();
    await Promise.race([browserExited, delay(3000)]);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => undefined);
}
