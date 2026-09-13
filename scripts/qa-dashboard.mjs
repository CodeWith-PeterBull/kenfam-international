import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'dev', 'dashboard-qa');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8012';
const email = process.env.AUREON_QA_EMAIL || 'admin@aureon.test';
const password = process.env.AUREON_QA_PASSWORD || 'password';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9452);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-dashboard-qa-'));
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
                if (message.error) pending.reject(new Error(message.error.message));
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
            this.pending.set(id, { resolve, reject });
            this.webSocket.send(JSON.stringify({ id, method, params }));
        });
    }

    once(method, timeout = 15000) {
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
const runtimeErrors = [];
const networkErrors = [];
const diagnostics = [];
let loaderEvidenceCaptured = false;

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
        client.send('DOM.enable'),
        client.send('CSS.enable'),
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
        const loaderVisible = await evaluate(`(() => {
            const loader = document.querySelector('[data-page-loader]');
            return Boolean(loader && !loader.hidden && document.documentElement.classList.contains('page-loader-visible'));
        })()`);

        if (loaderVisible && !loaderEvidenceCaptured) {
            const screenshot = await client.send('Page.captureScreenshot', {
                format: 'png',
                fromSurface: true,
                captureBeyondViewport: false,
            });
            await writeFile(path.join(outputDirectory, 'loader-light.png'), Buffer.from(screenshot.data, 'base64'));
            loaderEvidenceCaptured = true;
        }

        if (loaderVisible) {
            await evaluate(`new Promise((resolve) => {
                const loader = document.querySelector('[data-page-loader]');
                if (!loader || loader.hidden) {
                    resolve();
                    return;
                }
                const timer = window.setTimeout(resolve, 9000);
                window.addEventListener('aureon:page-loader-dismissed', () => {
                    window.clearTimeout(timer);
                    resolve();
                }, { once: true });
            })`, true);
        }

        await delay(150);
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
        const email = document.querySelector('input[name="email"]');
        const password = document.querySelector('input[name="password"]');
        email.value = ${JSON.stringify(email)};
        password.value = ${JSON.stringify(password)};
        email.dispatchEvent(new Event('input', { bubbles: true }));
        password.dispatchEvent(new Event('input', { bubbles: true }));
        document.querySelector('form').requestSubmit();
    })()`);
    await loggedIn;

    async function capture(name, width, height, mobile, { openNavigation = false, openSettings = false } = {}) {
        await setViewport(width, height, mobile);
        await navigate(`${siteUrl}/admin/dashboard?qa=${name}-${Date.now()}`);
        if (openNavigation) {
            await evaluate("document.querySelector('#mobile_btn').click()");
            await delay(300);
        }
        if (openSettings) {
            await evaluate("document.querySelector('[data-bs-target=\"#dashboard-settings\"]:not([tabindex=\"-1\"])').click()");
            await delay(200);
            const openedByTrigger = await evaluate("document.querySelector('#dashboard-settings').classList.contains('show')");
            if (!openedByTrigger) {
                await evaluate("bootstrap.Offcanvas.getOrCreateInstance(document.querySelector('#dashboard-settings')).show()");
            }
            await delay(300);
        }

        diagnostics.push(await evaluate(`(() => {
            const logo = document.querySelector('.header-left img');
            const sidebar = document.querySelector('#sidebar').getBoundingClientRect();
            const mobileButton = document.querySelector('#mobile_btn').getBoundingClientRect();
            const settings = document.querySelector('#dashboard-settings');
            const settingsTrigger = document.querySelector('[data-bs-target="#dashboard-settings"]:not([tabindex="-1"])');
            const welcome = getComputedStyle(document.querySelector('.aureon-welcome'));
            const loader = document.querySelector('[data-page-loader]');
            const logoRail = document.querySelector('.sidebar-logo');
            const logoVisible = (selector) => [...document.querySelectorAll(selector)]
                .some((logo) => getComputedStyle(logo).display !== 'none' && logo.getBoundingClientRect().width > 0);
            return {
                name: ${JSON.stringify(name)},
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                statCount: document.querySelectorAll('.aureon-stat').length,
                logoReady: Boolean(logo && logo.complete && logo.naturalWidth > 0),
                loaderCleared: !loader || loader.hidden,
                loaderDuration: Number(document.documentElement.dataset.pageLoaderDuration || 0),
                loaderBackground: loader ? getComputedStyle(loader).backgroundColor : null,
                logoRailBackground: getComputedStyle(logoRail).backgroundColor,
                normalLogoVisible: logoVisible('.header-left .logo-normal, .sidebar-logo .logo-normal'),
                lightLogoVisible: logoVisible('.header-left .logo-white, .sidebar-logo .logo-white'),
                sidebar: { left: sidebar.left, right: sidebar.right, width: sidebar.width },
                mobileButton: { left: mobileButton.left, right: mobileButton.right, width: mobileButton.width },
                settingsOpen: settings.classList.contains('show'),
                settingsTriggerReady: Boolean(settingsTrigger && getComputedStyle(settingsTrigger).display !== 'none'),
                settingsWidth: settings.getBoundingClientRect().width,
                settingsPreviewCount: settings.querySelectorAll('.aureon-sidebar-background img').length,
                settingsPreviewsReady: [...settings.querySelectorAll('.aureon-sidebar-background img')]
                    .every((image) => image.complete && image.naturalWidth > 0 && image.src.includes('sidebar-thumb-')),
                bodyClasses: document.body.className,
                theme: document.documentElement.dataset.bsTheme,
                layout: document.documentElement.dataset.layout,
                welcomeBackground: welcome.backgroundColor,
                welcomeArtwork: welcome.backgroundImage,
            };
        })()`));

        const screenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    async function captureSystemActivity(name, width, height, mobile) {
        await setViewport(width, height, mobile);
        await navigate(`${siteUrl}/admin/system-activity?qa=${name}-${Date.now()}`);
        await delay(300);

        const result = await evaluate(`(() => {
            const loader = document.querySelector('[data-page-loader]');
            const tableViewport = document.querySelector('#activity-table .table-responsive');
            const tableViewportStyle = tableViewport ? getComputedStyle(tableViewport) : null;
            return {
                name: ${JSON.stringify(name)},
                expectedWidth: ${width},
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                title: document.querySelector('.page-title h4')?.textContent.trim() || '',
                statCount: document.querySelectorAll('#system-activity-explorer .aureon-stat').length,
                filterCount: document.querySelectorAll('#system-activity-explorer input, #system-activity-explorer select').length,
                activityTableReady: Boolean(document.querySelector('.aureon-activity-table')),
                livewireReady: [...document.querySelectorAll('*')].some((element) => element.hasAttribute('wire:id')),
                governanceLinkReady: Boolean(document.querySelector('#sidebar-menu a[href*="system-activity"]')),
                logViewerLinkReady: Boolean(document.querySelector('a[href$="/logs"]')),
                loaderCleared: !loader || loader.hidden,
                loaderDuration: Number(document.documentElement.dataset.pageLoaderDuration || 0),
                tableViewportWidth: tableViewport?.getBoundingClientRect().width || 0,
                tableOverflowX: tableViewportStyle?.overflowX || null,
                tableContained: Boolean(
                    tableViewport
                    && tableViewport.getBoundingClientRect().width <= innerWidth + 1
                    && ['auto', 'scroll'].includes(tableViewportStyle.overflowX)
                ),
                overflowTrace: [...document.querySelectorAll('body *')]
                    .map((element) => {
                        const bounds = element.getBoundingClientRect();
                        return {
                            tag: element.tagName.toLowerCase(),
                            id: element.id || null,
                            className: typeof element.className === 'string' ? element.className : null,
                            left: Math.round(bounds.left),
                            right: Math.round(bounds.right),
                            width: Math.round(bounds.width),
                            scrollWidth: element.scrollWidth,
                            overflowX: getComputedStyle(element).overflowX,
                        };
                    })
                    .filter((element) => element.right > innerWidth + 1 || element.left < -1)
                    .sort((first, second) => second.right - first.right)
                    .slice(0, 15),
            };
        })()`);

        const screenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));

        return result;
    }

    async function captureConfigurationPage(name, pathName, expectedTitle, width, height, mobile, module) {
        await setViewport(width, height, mobile);
        await navigate(`${siteUrl}${pathName}?qa=${name}-${Date.now()}`);
        await delay(500);

        const result = await evaluate(`(() => {
            const loader = document.querySelector('[data-page-loader]');
            const frames = [...document.querySelectorAll('iframe')];
            return {
                name: ${JSON.stringify(name)},
                module: ${JSON.stringify(module)},
                expectedTitle: ${JSON.stringify(expectedTitle)},
                expectedWidth: ${width},
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                title: document.querySelector('.page-title h4')?.textContent.trim() || '',
                pageWrapperCount: document.querySelectorAll('.main-wrapper > .page-wrapper').length,
                livewireReady: [...document.querySelectorAll('*')]
                    .some((element) => element.hasAttribute('wire:id')),
                institutionInputCount: document.querySelectorAll('#institution-details-editor input').length,
                brandPreviewCount: document.querySelectorAll('.aureon-brand-preview img').length,
                mailPreviewCount: document.querySelectorAll('.aureon-mail-preview').length,
                reportPreviewCount: document.querySelectorAll('.aureon-report-preview').length,
                reportDownloadCount: document.querySelectorAll('a[href*="report-download"]').length,
                configurationNavigationReady: Boolean(document.querySelector('#sidebar-menu a[href*="institution-details"]'))
                    && Boolean(document.querySelector('#sidebar-menu a[href*="communication-templates"]')),
                framesContained: frames.every((frame) => {
                    const bounds = frame.getBoundingClientRect();
                    return bounds.left >= -1 && bounds.right <= innerWidth + 1 && bounds.width > 0;
                }),
                loaderCleared: !loader || loader.hidden,
                loaderDuration: Number(document.documentElement.dataset.pageLoaderDuration || 0),
            };
        })()`);

        const screenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));

        return result;
    }

    async function captureAccessPage(name, pathName, expectedTitle, width, height, mobile, module) {
        await setViewport(width, height, mobile);
        await navigate(`${siteUrl}${pathName}?qa=${name}-${Date.now()}`);
        await delay(500);

        const result = await evaluate(`(() => {
            const loader = document.querySelector('[data-page-loader]');
            const tableViewport = document.querySelector('#user-management .table-responsive, #roles-and-permissions-manager .table-responsive');
            const tableStyle = tableViewport ? getComputedStyle(tableViewport) : null;
            return {
                name: ${JSON.stringify(name)},
                module: ${JSON.stringify(module)},
                expectedTitle: ${JSON.stringify(expectedTitle)},
                expectedWidth: ${width},
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                title: document.querySelector('.page-title h4')?.textContent.trim() || '',
                livewireReady: [...document.querySelectorAll('*')].some((element) => element.hasAttribute('wire:id')),
                userTableReady: Boolean(document.querySelector('.aureon-user-table')),
                userFilterCount: document.querySelectorAll('#user-management input, #user-management select').length,
                addUserReady: Boolean(document.querySelector('#user-management [wire\\\\:click="openCreate"]')),
                roleTableReady: Boolean(document.querySelector('.aureon-role-table')),
                permissionCatalogueReady: Boolean(document.querySelector('#permission-catalogue-title')),
                addRoleReady: Boolean(document.querySelector('#roles-and-permissions-manager [wire\\\\:click="openCreate"]')),
                accessNavigationReady: Boolean(document.querySelector('#sidebar-menu a[href*="/admin/users"]'))
                    && Boolean(document.querySelector('#sidebar-menu a[href*="roles-and-permissions"]')),
                tableContained: Boolean(
                    tableViewport
                    && tableViewport.getBoundingClientRect().width <= innerWidth + 1
                    && ['auto', 'scroll'].includes(tableStyle?.overflowX)
                ),
                loaderCleared: !loader || loader.hidden,
                loaderDuration: Number(document.documentElement.dataset.pageLoaderDuration || 0),
            };
        })()`);

        const screenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));

        return result;
    }

    async function captureCommercePage(name, pathName, expectedTitle, width, height, mobile, module) {
        await setViewport(width, height, mobile);
        await navigate(`${siteUrl}${pathName}?qa=${name}-${Date.now()}`);
        await delay(500);

        const result = await evaluate(`(() => {
            const loader = document.querySelector('[data-page-loader]');
            const tableViewports = [...document.querySelectorAll('#commerce-product-catalog .table-responsive, #commerce-category-manager .table-responsive, #commerce-inventory-manager .table-responsive')];
            return {
                name: ${JSON.stringify(name)},
                module: ${JSON.stringify(module)},
                expectedTitle: ${JSON.stringify(expectedTitle)},
                expectedWidth: ${width},
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                title: document.querySelector('.page-title h4')?.textContent.trim() || '',
                livewireCount: document.querySelectorAll('[wire\\\\:id]').length,
                statCount: document.querySelectorAll('.aureon-stat').length,
                productTableReady: Boolean(document.querySelector('.aureon-commerce-table')),
                categoryTableReady: Boolean(document.querySelector('.aureon-category-table')),
                movementTableReady: Boolean(document.querySelector('.aureon-movement-table')),
                addProductReady: Boolean(document.querySelector('#commerce-product-catalog [wire\\\\:click="openCreate"]')),
                addCategoryReady: Boolean(document.querySelector('#commerce-category-manager [wire\\\\:click="openCreate"]')),
                adjustmentControlsReady: Boolean(document.querySelector('#commerce-inventory-manager')),
                commerceNavigationReady: Boolean(document.querySelector('#sidebar-menu a[href*="commerce/catalog"]'))
                    && Boolean(document.querySelector('#sidebar-menu a[href*="commerce/inventory"]')),
                tablesContained: tableViewports.length > 0 && tableViewports.every((viewport) => {
                    const style = getComputedStyle(viewport);
                    return viewport.getBoundingClientRect().width <= innerWidth + 1
                        && ['auto', 'scroll'].includes(style.overflowX);
                }),
                loaderCleared: !loader || loader.hidden,
                loaderDuration: Number(document.documentElement.dataset.pageLoaderDuration || 0),
            };
        })()`);

        const screenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));

        return result;
    }

    await evaluate("localStorage.removeItem('laravel-aureon-dashboard-settings'); localStorage.removeItem('laravel-aureon-dashboard-theme')");
    await capture('desktop', 1440, 1000, false);
    await capture('desktop-settings', 1440, 1000, false, { openSettings: true });
    await evaluate("bootstrap.Offcanvas.getOrCreateInstance(document.querySelector('#dashboard-settings')).hide()");
    await delay(250);

    await evaluate("document.querySelector('[data-dashboard-theme-toggle]').click()");
    await delay(600);
    const darkTheme = await evaluate("document.documentElement.dataset.bsTheme");
    const darkAppearance = await evaluate(`(() => {
        const rail = document.querySelector('.sidebar-logo');
        const loader = document.querySelector('[data-page-loader]');
        const logoVisible = (selector) => [...document.querySelectorAll(selector)]
            .some((logo) => getComputedStyle(logo).display !== 'none' && logo.getBoundingClientRect().width > 0);
        return {
            loaderBackground: getComputedStyle(loader).backgroundColor,
            logoRailBackground: getComputedStyle(rail).backgroundColor,
            normalLogoVisible: logoVisible('.header-left .logo-normal, .sidebar-logo .logo-normal'),
            lightLogoVisible: logoVisible('.header-left .logo-white, .sidebar-logo .logo-white'),
        };
    })()`);

    const hoverPoint = await evaluate(`(() => {
        const bounds = document.querySelector('#sidebar-menu a[href*="blank"]').getBoundingClientRect();
        return { x: bounds.left + bounds.width / 2, y: bounds.top + bounds.height / 2 };
    })()`);
    await client.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: hoverPoint.x, y: hoverPoint.y });
    await delay(250);
    const darkHover = await evaluate(`(() => {
        const link = document.querySelector('#sidebar-menu a[href*="blank"]');
        const sidebar = document.querySelector('#sidebar');
        const parse = (value) => (value.match(/[\\d.]+/g) || []).map(Number);
        const text = parse(getComputedStyle(link).color);
        const layer = parse(getComputedStyle(link).backgroundColor);
        const base = parse(getComputedStyle(sidebar).backgroundColor);
        const alpha = layer.length > 3 ? layer[3] : 1;
        const background = layer.slice(0, 3).map((channel, index) => channel * alpha + (base[index] || 0) * (1 - alpha));
        const luminance = (rgb) => {
            const values = rgb.slice(0, 3).map((channel) => {
                const value = channel / 255;
                return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
            });
            return 0.2126 * values[0] + 0.7152 * values[1] + 0.0722 * values[2];
        };
        const lighter = Math.max(luminance(text), luminance(background));
        const darker = Math.min(luminance(text), luminance(background));
        return {
            color: getComputedStyle(link).color,
            background: getComputedStyle(link).backgroundColor,
            sidebarBackground: getComputedStyle(sidebar).backgroundColor,
            contrast: Number(((lighter + 0.05) / (darker + 0.05)).toFixed(2)),
        };
    })()`);
    await client.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 800, y: 20 });

    await evaluate(`(() => {
        const set = (key, value) => {
            const input = [...document.querySelectorAll('[data-dashboard-setting]')]
                .find((candidate) => candidate.dataset.dashboardSetting === key && candidate.value === value);
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        set('layout', 'detached');
        set('width', 'box');
    })()`);
    await delay(200);
    const alternateLayout = await evaluate(`(() => {
        const sidebar = document.querySelector('#sidebar').getBoundingClientRect();
        const page = document.querySelector('.page-wrapper').getBoundingClientRect();
        return {
            layout: document.documentElement.dataset.layout,
            width: document.documentElement.dataset.width,
            boxed: document.body.classList.contains('layout-box-mode'),
            sidebarLeft: sidebar.left,
            sidebarTop: sidebar.top,
            pageLeft: page.left,
        };
    })()`);

    await evaluate(`(() => {
        const set = (key, value) => {
            const input = [...document.querySelectorAll('[data-dashboard-setting]')]
                .find((candidate) => candidate.dataset.dashboardSetting === key && candidate.value === value);
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        set('palette', 'teal');
        set('layout', 'default');
        set('width', 'fluid');
        set('sidebar', 'dark');
        set('sidebarBackground', 'sidebarbg2');
    })()`);
    await delay(250);
    const sidebarArtwork = await evaluate(`(() => ({
        sidebar: document.documentElement.dataset.sidebar,
        sidebarBackground: document.documentElement.dataset.sidebarBackground,
        artwork: getComputedStyle(document.querySelector('#sidebar')).backgroundImage,
        innerBackground: getComputedStyle(document.querySelector('.sidebar-inner')).backgroundColor,
        menuBackground: getComputedStyle(document.querySelector('.sidebar-menu')).backgroundColor,
    }))()`);
    const sidebarArtworkScreenshot = await client.send('Page.captureScreenshot', {
        format: 'png',
        fromSurface: true,
        captureBeyondViewport: false,
    });
    await writeFile(path.join(outputDirectory, 'desktop-sidebar-artwork.png'), Buffer.from(sidebarArtworkScreenshot.data, 'base64'));

    await evaluate(`(() => {
        const input = [...document.querySelectorAll('[data-dashboard-setting]')]
            .find((candidate) => candidate.dataset.dashboardSetting === 'layout' && candidate.value === 'mini');
        input.checked = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    })()`);
    await delay(250);
    const customizedSettings = await evaluate(`(() => ({
        stored: JSON.parse(localStorage.getItem('laravel-aureon-dashboard-settings')),
        theme: document.documentElement.dataset.bsTheme,
        palette: document.documentElement.dataset.color,
        layout: document.documentElement.dataset.layout,
        sidebar: document.documentElement.dataset.sidebar,
        sidebarBackground: document.documentElement.dataset.sidebarBackground,
        miniSidebar: document.body.classList.contains('mini-sidebar'),
        compactLogoReady: (() => {
            const logo = document.querySelector('.sidebar-logo .logo-small img');
            return Boolean(logo && logo.complete && logo.naturalWidth > 0 && logo.getBoundingClientRect().width > 0);
        })(),
        compactLogoState: [...document.querySelectorAll('.logo-small')].map((logo) => ({
            parent: logo.closest('.header-left') ? 'header' : 'sidebar',
            display: getComputedStyle(logo).display,
            visibility: getComputedStyle(logo).visibility,
            width: logo.getBoundingClientRect().width,
            imageWidth: logo.querySelector('img')?.getBoundingClientRect().width || 0,
            naturalWidth: logo.querySelector('img')?.naturalWidth || 0,
        })),
        sidebarArtwork: getComputedStyle(document.querySelector('#sidebar')).backgroundImage,
        sidebarInnerBackground: getComputedStyle(document.querySelector('.sidebar-inner')).backgroundColor,
        sidebarMenuBackground: getComputedStyle(document.querySelector('.sidebar-menu')).backgroundColor,
        welcomeBackground: getComputedStyle(document.querySelector('.aureon-welcome')).backgroundColor,
    }))()`);
    const customizedScreenshot = await client.send('Page.captureScreenshot', {
        format: 'png',
        fromSurface: true,
        captureBeyondViewport: false,
    });
    await writeFile(path.join(outputDirectory, 'desktop-customized.png'), Buffer.from(customizedScreenshot.data, 'base64'));

    await evaluate("document.querySelector('[data-dashboard-settings-reset]').click()");
    await delay(150);
    const resetSettings = await evaluate(`(() => ({
        stored: JSON.parse(localStorage.getItem('laravel-aureon-dashboard-settings')),
        theme: document.documentElement.dataset.bsTheme,
        layout: document.documentElement.dataset.layout,
        width: document.documentElement.dataset.width,
        palette: document.documentElement.dataset.color,
        sidebar: document.documentElement.dataset.sidebar,
        sidebarBackground: document.documentElement.dataset.sidebarBackground,
        miniSidebar: document.body.classList.contains('mini-sidebar'),
    }))()`);

    await capture('mobile-navigation', 390, 844, true, { openNavigation: true });
    await capture('mobile-settings', 390, 844, true, { openSettings: true });

    const activityDiagnostics = [
        await captureSystemActivity('system-activity-mobile', 390, 844, false),
        await captureSystemActivity('system-activity-desktop', 1440, 1000, false),
    ];

    const configurationDiagnostics = [
        await captureConfigurationPage('institution-details-mobile', '/admin/institution-details', 'Institution details', 390, 844, true, 'institution'),
        await captureConfigurationPage('institution-details-desktop', '/admin/institution-details', 'Institution details', 1440, 1000, false, 'institution'),
        await captureConfigurationPage('communication-templates-mobile', '/admin/communication-templates', 'Communication templates', 390, 844, true, 'communications'),
        await captureConfigurationPage('communication-templates-desktop', '/admin/communication-templates', 'Communication templates', 1440, 1000, false, 'communications'),
    ];

    const accessDiagnostics = [
        await captureAccessPage('user-management-mobile', '/admin/users', 'User management', 390, 844, true, 'users'),
        await captureAccessPage('user-management-desktop', '/admin/users', 'User management', 1440, 1000, false, 'users'),
        await captureAccessPage('roles-permissions-mobile', '/admin/roles-and-permissions', 'Roles and permissions', 390, 844, true, 'roles'),
        await captureAccessPage('roles-permissions-desktop', '/admin/roles-and-permissions', 'Roles and permissions', 1440, 1000, false, 'roles'),
    ];

    const commerceDiagnostics = [
        await captureCommercePage('commerce-catalog-mobile', '/admin/commerce/catalog', 'Product catalog', 390, 844, true, 'catalog'),
        await captureCommercePage('commerce-catalog-desktop', '/admin/commerce/catalog', 'Product catalog', 1440, 1000, false, 'catalog'),
        await captureCommercePage('commerce-inventory-mobile', '/admin/commerce/inventory', 'Inventory', 390, 844, true, 'inventory'),
        await captureCommercePage('commerce-inventory-desktop', '/admin/commerce/inventory', 'Inventory', 1440, 1000, false, 'inventory'),
    ];

    const failures = [];
    for (const result of diagnostics) {
        if (result.documentWidth > result.viewportWidth + 1) failures.push(`${result.name}: horizontal overflow`);
        if (result.statCount !== 8) failures.push(`${result.name}: expected eight statistic cards`);
        if (!result.logoReady) failures.push(`${result.name}: logo did not render`);
        if (!result.loaderCleared) failures.push(`${result.name}: loader did not clear`);
        if (result.loaderDuration < 2800 || result.loaderDuration > 8500) failures.push(`${result.name}: loader duration is ${result.loaderDuration}ms`);
        if (result.theme === 'light' && result.logoRailBackground !== 'rgb(255, 255, 255)') failures.push(`${result.name}: light logo rail is not white`);
        if (result.theme === 'light' && (!result.normalLogoVisible || result.lightLogoVisible)) failures.push(`${result.name}: light logo variant is incorrect`);
        if (result.name === 'mobile-navigation' && result.sidebar.right <= 0) failures.push('mobile-navigation: sidebar did not open');
        if (result.name === 'mobile-navigation' && result.mobileButton.left < result.viewportWidth * 0.75) failures.push('mobile-navigation: close control is not at the far right');
        if (result.name.endsWith('settings') && !result.settingsOpen) failures.push(`${result.name}: settings offcanvas did not open`);
        if (result.name.endsWith('settings') && !result.settingsTriggerReady) failures.push(`${result.name}: settings trigger is unavailable`);
        if (result.name.endsWith('settings') && result.settingsPreviewCount !== 6) failures.push(`${result.name}: sidebar previews are incomplete`);
        if (result.name.endsWith('settings') && !result.settingsPreviewsReady) failures.push(`${result.name}: sidebar preview artwork did not render`);
        if (result.name === 'mobile-settings' && result.settingsWidth > result.viewportWidth + 1) failures.push('mobile-settings: offcanvas exceeds the viewport');
        if (!result.welcomeArtwork.includes('welcome-bg-01.svg')) failures.push(`${result.name}: welcome artwork is missing`);
    }
    if (darkTheme !== 'dark') failures.push('theme toggle did not activate dark mode');
    if (darkAppearance.loaderBackground === 'rgb(255, 255, 255)') failures.push('dark mode loader retained a white surface');
    if (darkAppearance.logoRailBackground === 'rgb(255, 255, 255)') failures.push('dark mode logo rail retained a white surface');
    if (darkAppearance.normalLogoVisible || !darkAppearance.lightLogoVisible) failures.push('dark mode logo variant is incorrect');
    if (darkHover.contrast < 4.5) failures.push(`dark sidebar hover contrast is ${darkHover.contrast}:1`);
    if (alternateLayout.layout !== 'detached' || alternateLayout.width !== 'box' || !alternateLayout.boxed) failures.push('detached boxed layout did not apply');
    if (alternateLayout.sidebarLeft < 5 || alternateLayout.sidebarTop < 50 || alternateLayout.pageLeft < 300) failures.push('detached layout geometry is incorrect');
    if (customizedSettings.palette !== 'teal' || customizedSettings.layout !== 'mini') failures.push('palette or compact layout did not apply');
    if (sidebarArtwork.sidebar !== 'dark' || sidebarArtwork.sidebarBackground !== 'sidebarbg2') failures.push('full sidebar artwork settings did not apply');
    if (!sidebarArtwork.artwork.includes('bg-02.jpg') || !sidebarArtwork.artwork.includes('0.64')) failures.push('full sidebar artwork or overlay did not render');
    if (sidebarArtwork.innerBackground !== 'rgba(0, 0, 0, 0)' || sidebarArtwork.menuBackground !== 'rgba(0, 0, 0, 0)') failures.push('full sidebar content surfaces obscure the selected artwork');
    if (!customizedSettings.miniSidebar) failures.push('compact layout did not collapse the sidebar');
    if (!customizedSettings.compactLogoReady) failures.push('compact layout did not render the Aureon icon');
    if (customizedSettings.sidebar !== 'dark' || customizedSettings.sidebarBackground !== 'sidebarbg2') failures.push('sidebar settings did not apply');
    if (!customizedSettings.sidebarArtwork.includes('bg-02.jpg') || !customizedSettings.sidebarArtwork.includes('0.64')) failures.push('selected sidebar artwork or overlay did not apply');
    if (customizedSettings.sidebarInnerBackground !== 'rgba(0, 0, 0, 0)' || customizedSettings.sidebarMenuBackground !== 'rgba(0, 0, 0, 0)') failures.push('sidebar content surfaces obscure the selected artwork');
    if (!customizedSettings.welcomeBackground.includes('40, 101, 107')) failures.push('welcome banner did not follow the selected primary color');
    if (resetSettings.theme !== 'light' || resetSettings.layout !== 'default' || resetSettings.width !== 'fluid') failures.push('reset did not restore base appearance');
    if (resetSettings.palette !== 'wine' || resetSettings.sidebar !== 'theme' || resetSettings.sidebarBackground !== 'none') failures.push('reset did not restore Aureon theme defaults');
    if (resetSettings.miniSidebar) failures.push('reset left the compact sidebar active');
    for (const result of activityDiagnostics) {
        if (result.viewportWidth !== result.expectedWidth) failures.push(`${result.name}: expected a ${result.expectedWidth}px viewport, received ${result.viewportWidth}px`);
        if (result.documentWidth > result.viewportWidth + 1) failures.push(`${result.name}: horizontal overflow`);
        if (result.title !== 'System activity') failures.push(`${result.name}: page title is missing`);
        if (result.statCount !== 4) failures.push(`${result.name}: expected four activity statistic cards`);
        if (result.filterCount < 7) failures.push(`${result.name}: activity filters are incomplete`);
        if (!result.activityTableReady || !result.tableContained) failures.push(`${result.name}: activity table is not contained`);
        if (!result.livewireReady) failures.push(`${result.name}: Livewire component did not initialize`);
        if (!result.governanceLinkReady || !result.logViewerLinkReady) failures.push(`${result.name}: governance navigation is incomplete`);
        if (!result.loaderCleared) failures.push(`${result.name}: loader did not clear`);
        if (result.loaderDuration < 2800 || result.loaderDuration > 8500) failures.push(`${result.name}: loader duration is ${result.loaderDuration}ms`);
    }
    for (const result of configurationDiagnostics) {
        if (result.documentWidth > result.viewportWidth + 1) failures.push(`${result.name}: horizontal overflow`);
        if (result.title !== result.expectedTitle) failures.push(`${result.name}: page title is missing`);
        if (result.pageWrapperCount !== 1) failures.push(`${result.name}: shared dashboard wrapper is duplicated`);
        if (!result.configurationNavigationReady) failures.push(`${result.name}: configuration navigation is incomplete`);
        if (!result.framesContained) failures.push(`${result.name}: a preview frame exceeds the viewport`);
        if (!result.loaderCleared) failures.push(`${result.name}: loader did not clear`);
        if (result.loaderDuration < 2800 || result.loaderDuration > 8500) failures.push(`${result.name}: loader duration is ${result.loaderDuration}ms`);
        if (result.module === 'institution' && (!result.livewireReady || result.institutionInputCount < 12 || result.brandPreviewCount !== 2)) {
            failures.push(`${result.name}: Institution Details editor is incomplete`);
        }
        if (result.module === 'communications' && (result.mailPreviewCount !== 1 || result.reportPreviewCount !== 2 || result.reportDownloadCount !== 2)) {
            failures.push(`${result.name}: communication previews are incomplete`);
        }
    }
    for (const result of accessDiagnostics) {
        if (result.viewportWidth !== result.expectedWidth) failures.push(`${result.name}: expected a ${result.expectedWidth}px viewport, received ${result.viewportWidth}px`);
        if (result.documentWidth > result.viewportWidth + 1) failures.push(`${result.name}: horizontal overflow`);
        if (result.title !== result.expectedTitle) failures.push(`${result.name}: page title is missing`);
        if (!result.livewireReady) failures.push(`${result.name}: Livewire component did not initialize`);
        if (!result.accessNavigationReady) failures.push(`${result.name}: users and access navigation is incomplete`);
        if (!result.tableContained) failures.push(`${result.name}: access table is not contained`);
        if (!result.loaderCleared) failures.push(`${result.name}: loader did not clear`);
        if (result.loaderDuration < 2800 || result.loaderDuration > 8500) failures.push(`${result.name}: loader duration is ${result.loaderDuration}ms`);
        if (result.module === 'users' && (!result.userTableReady || result.userFilterCount < 4 || !result.addUserReady)) {
            failures.push(`${result.name}: user-management controls are incomplete`);
        }
        if (result.module === 'roles' && (!result.roleTableReady || !result.permissionCatalogueReady || !result.addRoleReady)) {
            failures.push(`${result.name}: role-management controls are incomplete`);
        }
    }
    for (const result of commerceDiagnostics) {
        if (result.viewportWidth !== result.expectedWidth) failures.push(`${result.name}: expected a ${result.expectedWidth}px viewport, received ${result.viewportWidth}px`);
        if (result.documentWidth > result.viewportWidth + 1) failures.push(`${result.name}: horizontal overflow`);
        if (result.title !== result.expectedTitle) failures.push(`${result.name}: page title is missing`);
        if (result.livewireCount < (result.module === 'catalog' ? 2 : 1)) failures.push(`${result.name}: Commerce Livewire components did not initialize`);
        if (result.statCount !== 4) failures.push(`${result.name}: expected four Commerce statistic cards`);
        if (!result.commerceNavigationReady) failures.push(`${result.name}: Commerce navigation is incomplete`);
        if (!result.tablesContained) failures.push(`${result.name}: a Commerce table is not contained`);
        if (!result.loaderCleared) failures.push(`${result.name}: loader did not clear`);
        if (result.loaderDuration < 2800 || result.loaderDuration > 8500) failures.push(`${result.name}: loader duration is ${result.loaderDuration}ms`);
        if (result.module === 'catalog' && (!result.productTableReady || !result.categoryTableReady || !result.addProductReady || !result.addCategoryReady)) {
            failures.push(`${result.name}: catalog administration controls are incomplete`);
        }
        if (result.module === 'inventory' && (!result.productTableReady || !result.movementTableReady || !result.adjustmentControlsReady)) {
            failures.push(`${result.name}: inventory administration controls are incomplete`);
        }
    }
    failures.push(...runtimeErrors, ...networkErrors);

    await writeFile(
        path.join(outputDirectory, 'diagnostics.json'),
        `${JSON.stringify({ diagnostics, activityDiagnostics, configurationDiagnostics, accessDiagnostics, commerceDiagnostics, darkTheme, darkAppearance, darkHover, alternateLayout, sidebarArtwork, customizedSettings, resetSettings, runtimeErrors, networkErrors }, null, 2)}\n`,
    );

    if (failures.length) throw new Error(failures.join('\n'));
    process.stdout.write(`${JSON.stringify({ diagnostics, activityDiagnostics, configurationDiagnostics, accessDiagnostics, commerceDiagnostics, darkTheme, darkAppearance, darkHover, alternateLayout, sidebarArtwork, customizedSettings, resetSettings }, null, 2)}\n`);
} finally {
    client?.close();
    if (process.platform === 'win32' && browser.pid) {
        await new Promise((resolve) => {
            const killer = spawn('taskkill', ['/PID', String(browser.pid), '/T', '/F'], { stdio: 'ignore' });
            killer.once('exit', resolve);
            killer.once('error', () => {
                browser.kill();
                resolve();
            });
        });
    } else {
        browser.kill();
        await Promise.race([
            new Promise((resolve) => browser.once('exit', resolve)),
            delay(1200),
        ]);
    }
    for (let attempt = 0; attempt < 4; attempt += 1) {
        try {
            await rm(profileDirectory, { recursive: true, force: true });
            break;
        } catch (error) {
            if (error.code !== 'EBUSY') throw error;
            if (attempt === 3) break;
            await delay(300);
        }
    }
}
