/**
 * Authenticated browser QA for the full-width Commerce POS terminal.
 *
 * Prerequisites: built Vite assets, demo data, an open till for full terminal
 * and cashier-history QA, and a Laravel server at AUREON_QA_URL
 * (default http://127.0.0.1:8012).
 * Set AUREON_QA_ADMIN_ONLY=1 to validate register/till administration without
 * an open cashier session.
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
    : path.join(root, '.docs', 'dev', 'commerce-pos-qa');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8012';
const email = process.env.AUREON_QA_EMAIL || 'admin@aureon.test';
const password = process.env.AUREON_QA_PASSWORD || 'password';
const receiptPath = process.env.AUREON_QA_RECEIPT_PATH || '';
const administrationOnly = process.env.AUREON_QA_ADMIN_ONLY === '1';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9472);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-pos-qa-'));
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
    await client.send('Page.addScriptToEvaluateOnNewDocument', {
        source: `(() => {
            window.__aureonReceiptPrintEvents = [];
            window.addEventListener('commerce:receipt-print', (event) => {
                window.__aureonReceiptPrintEvents.push(event.detail);
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

    async function waitFor(expression, timeout = 7000) {
        const startedAt = Date.now();
        while (Date.now() - startedAt < timeout) {
            const value = await evaluate(expression);
            if (value) return value;
            await delay(120);
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

    await setViewport(1440, 1000, false);
    await navigate(`${siteUrl}/login`);
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

    async function setTheme(mode) {
        await evaluate(`(() => {
            const key = 'laravel-aureon-dashboard-settings';
            let value = {};
            try { value = JSON.parse(localStorage.getItem(key) || '{}'); } catch { value = {}; }
            localStorage.setItem(key, JSON.stringify({ ...value, mode: ${JSON.stringify(mode)} }));
        })()`);
    }

    async function prepareTerminal(mode) {
        await setTheme(mode);
        await navigate(`${siteUrl}/pos?qa=${mode}-${Date.now()}`);
        assert(Boolean(await waitFor("Boolean(document.querySelector('[data-pos-terminal]'))")), 'Terminal root did not render');
        await evaluate("document.querySelector('.pos-product:not(:disabled)')?.click()");
        assert(Boolean(await waitFor("Boolean(document.querySelector('.pos-cart-line'))")), 'Product selection did not update the sale');
        await evaluate("document.querySelector('.pos-money-input button')?.click()");
        await delay(220);
    }

    async function capture(name, width, height, mobile, mode) {
        await setViewport(width, height, mobile);
        await prepareTerminal(mode);
        await evaluate(`Promise.all([...document.querySelectorAll('.pos-product img')].map((image) => new Promise((resolve) => {
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
            const images = [...document.querySelectorAll('.pos-product img')];
            const labeled = (field) => field.getAttribute('aria-label') || field.getAttribute('aria-labelledby') || field.closest('label') || (field.id && document.querySelector('label[for="' + CSS.escape(field.id) + '"]'));
            const textOverflow = [...document.querySelectorAll('.pos-product__copy strong, .pos-button, .pos-session-bar strong')].filter((element) => element.scrollWidth > element.clientWidth + 2 && getComputedStyle(element).whiteSpace !== 'normal').length;
            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                theme: document.documentElement.dataset.theme,
                loaderHidden: Boolean(document.querySelector('[data-page-loader]')?.hidden),
                topbar: visible('.pos-topbar'),
                terminal: visible('.pos-terminal-grid'),
                products: document.querySelectorAll('.pos-product').length,
                cartLines: document.querySelectorAll('.pos-cart-line').length,
                salePanel: visible('.pos-sale-panel'),
                images: images.length,
                loadedImages: images.filter((image) => image.complete && image.naturalWidth > 0).length,
                unlabeledButtons: [...document.querySelectorAll('button')].filter((button) => !button.textContent.trim() && !button.getAttribute('aria-label') && !button.getAttribute('title')).length,
                unlabeledFields: [...document.querySelectorAll('input:not([type="hidden"]), select, textarea')].filter((field) => !labeled(field)).length,
                duplicateIds: [...document.querySelectorAll('[id]')].map((element) => element.id).filter((id, index, ids) => ids.indexOf(id) !== index).length,
                textOverflow,
            };
        })()`);
        diagnostics.push(snapshot);
        assert(snapshot.viewportWidth === snapshot.documentWidth, `${name}: document overflows viewport`);
        assert(snapshot.theme === mode, `${name}: expected ${mode} theme`);
        assert(snapshot.loaderHidden, `${name}: loader did not settle`);
        assert(snapshot.topbar && snapshot.terminal && snapshot.salePanel, `${name}: terminal structure is incomplete`);
        assert(snapshot.products >= 6 && snapshot.cartLines === 1, `${name}: product browser or sale state is incomplete`);
        assert(snapshot.images === snapshot.loadedImages, `${name}: product image failed to load`);
        assert(snapshot.unlabeledButtons === 0, `${name}: button without an accessible name`);
        assert(snapshot.unlabeledFields === 0, `${name}: form control without an associated label`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate element IDs found`);
        assert(snapshot.textOverflow === 0, `${name}: operational text overflows its control`);

        const screenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    async function captureCashierSalesHistory(name, width, height, mobile, mode) {
        await setViewport(width, height, mobile);
        await setTheme(mode);
        await navigate(`${siteUrl}/pos?qa=${name}-${Date.now()}`);
        assert(Boolean(await waitFor("Boolean(document.querySelector('[data-cashier-sales-history][data-surface=\"terminal\"]'))")), `${name}: cashier history did not render`);

        await evaluate(`(() => {
            const disclosure = document.querySelector('.cashier-sales-history__disclosure');
            if (disclosure) disclosure.open = true;
            document.querySelector('.cashier-sales-history__tabs button:last-child')?.click();
        })()`);
        assert(Boolean(await waitFor(`(() => {
            const historyTab = document.querySelector('.cashier-sales-history__tabs button:last-child');
            return historyTab?.getAttribute('aria-selected') === 'true'
                && document.querySelectorAll('.cashier-sales-history__sale').length > 0;
        })()`)), `${name}: historical sales did not load`);
        await evaluate("document.querySelector('[data-cashier-sales-history]')?.scrollIntoView({ block: 'center' })");
        await delay(220);

        const snapshot = await evaluate(`(() => {
            const root = document.querySelector('[data-cashier-sales-history][data-surface="terminal"]');
            const disclosure = root?.querySelector('.cashier-sales-history__disclosure');
            const rows = [...(root?.querySelectorAll('.cashier-sales-history__sale') || [])];
            const controls = [...(root?.querySelectorAll('button, select, a[href]') || [])];
            const rootBox = root?.getBoundingClientRect();
            const paintedRows = rows.every((row) => {
                const style = getComputedStyle(row);
                return style.backgroundColor !== 'rgba(0, 0, 0, 0)' && style.borderStyle !== 'none';
            });
            const named = (element) => element.textContent.trim() || element.getAttribute('aria-label') || element.getAttribute('title');

            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                theme: document.documentElement.dataset.theme,
                surface: root?.dataset.surface,
                disclosureOpen: Boolean(disclosure?.open),
                activeTab: root?.querySelector('[role="tab"][aria-selected="true"]')?.textContent.trim(),
                tabs: root?.querySelectorAll('[role="tab"]').length || 0,
                metrics: root?.querySelectorAll('.cashier-sales-history__metrics > div').length || 0,
                rows: rows.length,
                paintedRows,
                receiptLinks: root?.querySelectorAll('.cashier-sales-history__receipt[target="_blank"]').length || 0,
                unlabeledControls: controls.filter((control) => !named(control)).length,
                duplicateIds: [...document.querySelectorAll('[id]')].map((element) => element.id).filter((id, index, ids) => ids.indexOf(id) !== index).length,
                viewportOverflow: document.documentElement.scrollWidth > innerWidth,
                componentOverflow: Boolean(root && root.scrollWidth > root.clientWidth + 2),
                componentInViewport: Boolean(rootBox && rootBox.left >= -1 && rootBox.right <= innerWidth + 1),
            };
        })()`);

        diagnostics.push(snapshot);
        assert(snapshot.path === '/pos', `${name}: wrong route rendered`);
        assert(snapshot.theme === mode, `${name}: expected ${mode} theme`);
        assert(snapshot.surface === 'terminal' && snapshot.disclosureOpen, `${name}: terminal disclosure is incomplete`);
        assert(snapshot.activeTab === 'All session sales' && snapshot.tabs === 2, `${name}: sales-history tabs are incomplete`);
        assert(snapshot.metrics === 3 && snapshot.rows > 0 && snapshot.paintedRows, `${name}: sales summary or rows are incomplete`);
        assert(snapshot.receiptLinks === snapshot.rows, `${name}: every visible sale needs a protected receipt action`);
        assert(snapshot.unlabeledControls === 0, `${name}: sales-history control lacks an accessible name`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate element IDs found`);
        assert(!snapshot.viewportOverflow && !snapshot.componentOverflow && snapshot.componentInViewport, `${name}: cashier history overflows its responsive surface`);

        const screenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    async function captureAdministration(name, route, width, height, mobile, mode, expectedCards) {
        await setViewport(width, height, mobile);
        await setTheme(mode);
        await navigate(`${siteUrl}${route}?qa=${mode}-${Date.now()}`);
        await evaluate('window.scrollTo(0, 0)');

        const snapshot = await evaluate(`(() => {
            const cards = [...document.querySelectorAll('.aureon-stat')];
            const boxes = cards.map((card) => card.getBoundingClientRect());
            const painted = cards.every((card) => {
                const style = getComputedStyle(card);
                return style.backgroundColor !== 'rgba(0, 0, 0, 0)' && style.borderStyle !== 'none';
            });
            const complete = cards.every((card) => card.querySelector('.aureon-stat__icon') && card.querySelector('h3') && card.querySelector('p'));
            const textOverflow = cards.flatMap((card) => [...card.querySelectorAll('h3, p')]).filter((element) => element.scrollWidth > element.clientWidth + 2).length;
            return {
                name: ${JSON.stringify(name)},
                path: location.pathname,
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                theme: document.documentElement.dataset.theme,
                loaderHidden: Boolean(document.querySelector('[data-page-loader]')?.hidden),
                cards: cards.length,
                painted,
                complete,
                stableDimensions: boxes.every((box) => box.width >= 220 && box.height >= 80),
                responsiveRows: ${mobile
                    ? 'new Set(boxes.map((box) => Math.round(box.top))).size === cards.length'
                    : 'new Set(boxes.map((box) => Math.round(box.top))).size === 1'},
                textOverflow,
                duplicateIds: [...document.querySelectorAll('[id]')].map((element) => element.id).filter((id, index, ids) => ids.indexOf(id) !== index).length,
            };
        })()`);
        diagnostics.push(snapshot);
        assert(snapshot.viewportWidth === snapshot.documentWidth, `${name}: document overflows viewport`);
        assert(snapshot.theme === mode, `${name}: expected ${mode} theme`);
        assert(snapshot.loaderHidden, `${name}: loader did not settle`);
        assert(snapshot.cards === expectedCards, `${name}: expected ${expectedCards} statistic cards`);
        assert(snapshot.painted && snapshot.complete, `${name}: statistic card presentation is incomplete`);
        assert(snapshot.stableDimensions, `${name}: statistic card dimensions are unstable`);
        assert(snapshot.responsiveRows, `${name}: statistic cards do not follow the expected responsive rows`);
        assert(snapshot.textOverflow === 0, `${name}: statistic text overflows its card`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate element IDs found`);

        const screenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    async function captureRegisterPrintConfiguration() {
        await setViewport(1080, 900, false);
        await setTheme('dark');
        await navigate(`${siteUrl}/admin/commerce/pos/registers?qa=printer-dialog-${Date.now()}`);
        // Double-escaped: the browser must receive the CSS-escaped attribute name wire\:click.
        await evaluate(`document.querySelector('button[wire\\\\:click="openCreate"]')?.click()`);
        assert(Boolean(await waitFor("Boolean(document.querySelector('.modal.show'))")), 'register print configuration dialog did not open');

        const dialog = await evaluate(`(() => {
            const modal = document.querySelector('.modal-dialog');
            const rect = modal?.getBoundingClientRect();
            const labeled = (field) => field.id && document.querySelector('label[for="' + CSS.escape(field.id) + '"]');
            const fields = [...document.querySelectorAll('#register-print-driver, #register-print-mode, #register-paper-width, #register-printer-name')];
            return {
                driver: document.querySelector('#register-print-driver')?.value,
                mode: document.querySelector('#register-print-mode')?.value,
                width: document.querySelector('#register-paper-width')?.value,
                fields: fields.length,
                unlabeledFields: fields.filter((field) => !labeled(field)).length,
                insideViewport: Boolean(rect && rect.left >= 0 && rect.right <= innerWidth && rect.top >= 0 && rect.bottom <= innerHeight),
                horizontalOverflow: Boolean(modal && modal.scrollWidth > modal.clientWidth + 1),
                duplicateIds: [...document.querySelectorAll('[id]')].map((element) => element.id).filter((id, index, ids) => ids.indexOf(id) !== index).length,
            };
        })()`);
        diagnostics.push({ name: 'register-printer-dialog-dark', ...dialog });
        assert(dialog.fields === 4 && dialog.unlabeledFields === 0, 'register print configuration controls are incomplete or unlabeled');
        assert(dialog.driver === 'browser' && dialog.mode === 'manual' && dialog.width === '80', 'register print configuration defaults are unstable');
        assert(dialog.insideViewport && !dialog.horizontalOverflow, 'register print configuration dialog is clipped or overflowing');
        assert(dialog.duplicateIds === 0, 'register print configuration dialog contains duplicate IDs');

        const screenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        await writeFile(path.join(outputDirectory, 'register-printer-dialog-dark.png'), Buffer.from(screenshot.data, 'base64'));
    }

    if (!administrationOnly) {
        await capture('terminal-desktop-light', 1440, 1000, false, 'light');
        await capture('terminal-laptop-dark', 1080, 900, false, 'dark');
        await capture('terminal-tablet-light', 820, 1080, false, 'light');
        await capture('terminal-mobile-dark', 390, 844, true, 'dark');
        await captureCashierSalesHistory('cashier-sales-history-desktop-light', 1440, 1000, false, 'light');
        await captureCashierSalesHistory('cashier-sales-history-mobile-dark', 390, 844, true, 'dark');
    }
    await captureAdministration('register-admin-desktop-light', '/admin/commerce/pos/registers', 1440, 1000, false, 'light', 3);
    await captureAdministration('till-admin-desktop-dark', '/admin/commerce/pos/tills', 1440, 1000, false, 'dark', 4);
    await captureAdministration('register-admin-mobile-dark', '/admin/commerce/pos/registers', 390, 844, true, 'dark', 3);
    await captureAdministration('till-admin-mobile-light', '/admin/commerce/pos/tills', 390, 844, true, 'light', 4);
    await captureRegisterPrintConfiguration();

    if (!administrationOnly && receiptPath) {
        await setViewport(820, 1080, false);
        await setTheme('light');
        await navigate(`${siteUrl}${receiptPath}`);
        const receipt = await evaluate(`(() => ({
            visible: Boolean(document.querySelector('.pos-receipt')?.getBoundingClientRect().height),
            lines: document.querySelectorAll('.pos-receipt tbody tr').length,
            printButton: Boolean(document.querySelector('[data-pos-print]')),
            pdfLink: Boolean(document.querySelector('a[href$="/pdf"]')),
            overflow: document.documentElement.scrollWidth > innerWidth,
            instruction: JSON.parse(document.querySelector('[data-pos-print-instruction]')?.textContent || '{}'),
            paperWidth: Number(document.querySelector('[data-pos-receipt]')?.dataset.posPaperWidth),
        }))()`);
        diagnostics.push({ name: 'receipt-desktop-light', ...receipt });
        assert(receipt.visible && receipt.lines > 0, 'receipt: sale lines did not render');
        assert(receipt.printButton && receipt.pdfLink, 'receipt: print or PDF action is missing');
        assert(!receipt.overflow, 'receipt: document overflows viewport');
        assert([58, 80].includes(receipt.paperWidth), 'receipt: thermal paper profile is invalid');
        assert(receipt.instruction.strategy === 'browser-dialog', 'receipt: browser print driver is not active');
        assert(receipt.instruction.supportsSilentPrinting === false, 'receipt: browser driver incorrectly claims silent printing');

        const screenScreenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        await writeFile(path.join(outputDirectory, 'receipt-desktop-light.png'), Buffer.from(screenScreenshot.data, 'base64'));

        await setTheme('dark');
        await navigate(`${siteUrl}${receiptPath}`);
        await client.send('Emulation.setEmulatedMedia', { media: 'print' });
        const printState = await evaluate(`(() => ({
            documentTheme: document.documentElement.dataset.theme,
            colorScheme: getComputedStyle(document.documentElement).colorScheme,
            pageBackground: getComputedStyle(document.documentElement).backgroundColor,
            shellBackground: getComputedStyle(document.body).backgroundColor,
            topbarHidden: getComputedStyle(document.querySelector('.pos-topbar')).display === 'none',
            actionsHidden: getComputedStyle(document.querySelector('.pos-receipt-actions')).display === 'none',
            receiptVisible: getComputedStyle(document.querySelector('.pos-receipt')).display !== 'none',
            tableBackground: getComputedStyle(document.querySelector('.pos-receipt table')).backgroundColor,
            sectionBackground: getComputedStyle(document.querySelector('.pos-receipt tbody')).backgroundColor,
            rowBackground: getComputedStyle(document.querySelector('.pos-receipt tbody tr')).backgroundColor,
            cellBackground: getComputedStyle(document.querySelector('.pos-receipt tbody td')).backgroundColor,
            cellColor: getComputedStyle(document.querySelector('.pos-receipt tbody td')).color,
            receiptWidth: document.querySelector('.pos-receipt').getBoundingClientRect().width,
            paperWidth: Number(document.querySelector('[data-pos-receipt]').dataset.posPaperWidth),
        }))()`);
        diagnostics.push({ name: 'receipt-print-media', ...printState });
        assert(printState.documentTheme === 'dark', 'receipt: dark-theme print precondition was not applied');
        assert(printState.topbarHidden && printState.actionsHidden && printState.receiptVisible, 'receipt: print media rules are incomplete');
        assert(printState.colorScheme === 'light', 'receipt: print output did not force a light paper color scheme');
        assert(
            [
                printState.pageBackground,
                printState.shellBackground,
                printState.tableBackground,
                printState.sectionBackground,
                printState.rowBackground,
                printState.cellBackground,
            ]
                .every((color) => color === 'rgb(255, 255, 255)'),
            'receipt: dark theme leaked into the printable page or line-item table',
        );
        assert(printState.cellColor === 'rgb(17, 17, 17)', 'receipt: printed line-item text does not have dark paper contrast');
        const expectedReceiptWidth = (printState.paperWidth - 8) * (96 / 25.4);
        assert(Math.abs(printState.receiptWidth - expectedReceiptWidth) <= 3, 'receipt: printable width does not match the register roll profile');

        const printScreenshot = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        await writeFile(path.join(outputDirectory, 'receipt-print-dark.png'), Buffer.from(printScreenshot.data, 'base64'));
        await client.send('Emulation.setEmulatedMedia', { media: 'screen' });

        if (receipt.instruction.mode === 'auto_prompt') {
            await navigate(`${siteUrl}${receiptPath}${receiptPath.includes('?') ? '&' : '?'}print=checkout`);
            const autoEvent = await waitFor('window.__aureonReceiptPrintEvents[0]', 3000);
            assert(autoEvent?.reason === 'checkout', 'receipt: checkout did not request the configured automatic print prompt');
            assert(autoEvent?.supportsSilentPrinting === false, 'receipt: automatic browser prompt crossed the silent-print boundary');
            diagnostics.push({ name: 'receipt-auto-prompt', event: autoEvent || null });
        }
    }

    assert(runtimeErrors.length === 0, `Runtime errors: ${runtimeErrors.join(' | ')}`);
    assert(networkErrors.length === 0, `Network errors: ${networkErrors.join(' | ')}`);
    await writeFile(path.join(outputDirectory, 'diagnostics.json'), JSON.stringify({ siteUrl, diagnostics, runtimeErrors, networkErrors, failures }, null, 2));
    if (failures.length > 0) throw new Error(failures.join('\n'));
    process.stdout.write(`Commerce POS QA passed with ${diagnostics.length} captures.\n`);
} finally {
    client?.close();
    const browserExited = new Promise((resolve) => browser.once('exit', resolve));
    browser.kill();
    await Promise.race([browserExited, delay(3000)]);
    await rm(profileDirectory, { recursive: true, force: true }).catch(() => undefined);
}
