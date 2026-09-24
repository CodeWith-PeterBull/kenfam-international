/** Browser UAT for the TravelTours public homepage, catalog, and tour detail. */
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'TravelTours', 'qa', process.env.AUREON_QA_PHASE === 'k2e' ? 'storefront-k2e' : 'storefront');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8011';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9491);
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'kenfam-travel-qa-'));
const browser = spawn(browserPath, [
    '--headless=new', '--disable-gpu', '--disable-background-networking',
    '--disable-extensions', '--hide-scrollbars', '--no-first-run',
    `--remote-debugging-port=${debuggingPort}`, `--user-data-dir=${profileDirectory}`, 'about:blank',
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

    close() { this.webSocket.close(); }
}

let client;

try {
    await waitForBrowser();
    const target = await fetchJson(`http://127.0.0.1:${debuggingPort}/json/new?about:blank`, { method: 'PUT' });
    client = new CdpClient(target.webSocketDebuggerUrl);
    await client.connect();
    await Promise.all([
        client.send('Page.enable'), client.send('Runtime.enable'),
        client.send('Network.enable'), client.send('Log.enable'),
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
        let response;
        try {
            response = await client.send('Runtime.evaluate', { expression, awaitPromise, returnByValue: true });
        } catch (error) {
            throw new Error(`${error.message} while evaluating: ${expression.slice(0, 180)}`);
        }
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

    async function setViewport(width, height, mobile = false) {
        await client.send('Emulation.clearDeviceMetricsOverride');
        await client.send('Emulation.setDeviceMetricsOverride', {
            width, height, deviceScaleFactor: 1, mobile, screenWidth: width, screenHeight: height,
        });
        await client.send('Emulation.setTouchEmulationEnabled', { enabled: mobile, maxTouchPoints: mobile ? 5 : 1 });
    }

    async function navigate(url) {
        const loaded = client.once('Page.loadEventFired');
        await client.send('Page.navigate', { url });
        await loaded;
        await evaluate('document.fonts.ready', true);
        await evaluate('window.AureonPageLoader?.dismiss?.()');
        await waitFor(`!document.querySelector('[data-page-loader]') || document.querySelector('[data-page-loader]').hidden`);
        await delay(350);
    }

    async function setTheme(mode) {
        await evaluate(`window.AureonTheme?.save?.({ ...window.AureonTheme.read(), mode: ${JSON.stringify(mode)} })`);
        await delay(200);
    }

    async function screenshot(name) {
        const capture = await client.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(capture.data, 'base64'));
    }

    async function inspect(name, expectedPath, expectedTheme, headerMode) {
        const snapshot = await evaluate(`(() => {
            const visible = (element) => Boolean(element && element.getClientRects().length);
            const named = (element) => element.textContent.trim() || element.getAttribute('aria-label') || element.getAttribute('title');
            const controls = [...document.querySelectorAll('main input:not([type="hidden"]), main select, main textarea')].filter(visible);
            const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
            const images = [...document.images].filter(visible);
            const rootStyle = getComputedStyle(document.documentElement);
            return {
                name: ${JSON.stringify(name)}, path: location.pathname,
                theme: document.documentElement.dataset.theme,
                title: document.querySelector('main h1')?.textContent.trim() || '',
                viewportWidth: innerWidth, documentWidth: document.documentElement.scrollWidth,
                overflowSources: [...document.querySelectorAll('body *')].filter((element) => element.getBoundingClientRect().right > innerWidth + 2 && getComputedStyle(element).position !== 'fixed').slice(0, 6).map((element) => ({ tag: element.tagName, className: String(element.className).slice(0, 100), right: Math.round(element.getBoundingClientRect().right) })),
                loaderHidden: !document.querySelector('[data-page-loader]') || document.querySelector('[data-page-loader]').hidden,
                footer: Boolean(document.querySelector('.travel-footer')),
                themeController: Boolean(document.querySelector('[data-theme-controller]')),
                stylesheet: Boolean(document.querySelector('link[href*="/build/css/storefront-"]')),
                script: Boolean(document.querySelector('script[src*="/build/js/storefront-"]')),
                duplicateIds: ids.filter((id, index) => ids.indexOf(id) !== index).length,
                unlabeledButtons: [...document.querySelectorAll('button')].filter((button) => visible(button) && !named(button)).length,
                unlabeledControls: controls.filter((control) => !control.closest('label') && (!control.id || !document.querySelector('label[for="' + CSS.escape(control.id) + '"]'))).length,
                brokenImages: images.filter((image) => image.complete && image.naturalWidth === 0).length,
                desktopHeader: visible(document.querySelector('.travel-header__desktop')),
                tabletHeader: visible(document.querySelector('.travel-header__tablet')),
                mobileHeader: visible(document.querySelector('.travel-header__mobile')),
                signIn: Boolean(document.querySelector('a[href$="/login"]')),
                signUp: Boolean(document.querySelector('a[href$="/register"]')),
                surface: getComputedStyle(document.body).backgroundColor,
                primary: rootStyle.getPropertyValue('--theme-primary').trim(),
                canonical: document.querySelector('link[rel="canonical"]')?.href || '',
                schema: document.querySelector('script[type="application/ld+json"]')?.textContent || '',
            };
        })()`);
        diagnostics.push(snapshot);
        assert(snapshot.path === expectedPath, `${name}: expected path ${expectedPath}, received ${snapshot.path}`);
        assert(snapshot.theme === expectedTheme, `${name}: expected ${expectedTheme} theme`);
        assert(snapshot.title, `${name}: main heading is absent`);
        assert(snapshot.documentWidth <= snapshot.viewportWidth + 1, `${name}: horizontal overflow detected`);
        assert(snapshot.loaderHidden, `${name}: page loader did not settle`);
        assert(snapshot.footer && snapshot.themeController, `${name}: base components are incomplete`);
        assert(snapshot.stylesheet && snapshot.script, `${name}: module Vite assets are missing`);
        assert(snapshot.duplicateIds === 0, `${name}: duplicate IDs detected`);
        assert(snapshot.unlabeledButtons === 0, `${name}: visible button lacks an accessible name`);
        assert(snapshot.unlabeledControls === 0, `${name}: visible form control lacks a label`);
        assert(snapshot.brokenImages === 0, `${name}: visible image failed to render`);
        assert(snapshot.signIn && snapshot.signUp, `${name}: guest workspace or sign-up action is absent`);
        assert(snapshot.primary, `${name}: centralized theme color was not resolved`);
        assert(snapshot.canonical.startsWith(siteUrl), `${name}: canonical URL does not match the test origin`);
        assert(snapshot.schema.includes('TravelAgency'), `${name}: TravelAgency structured data is absent`);
        assert(snapshot[`${headerMode}Header`], `${name}: ${headerMode} header is not active`);

        return snapshot;
    }

    await setViewport(1440, 960);
    await navigate(`${siteUrl}/`);
    await setTheme('light');
    const homeLight = await inspect('desktop-light-home', '/', 'light', 'desktop');
    assert(Boolean(await waitFor(`Boolean(document.querySelector('[data-travel-hero-slider][data-hero-ready="true"]'))`)), 'Homepage hero did not initialize');
    await evaluate(`(() => {
        const slider = document.querySelector('[data-travel-hero-slider]')?.swiper;
        slider?.autoplay?.stop();
        slider?.slideToLoop(0, 0);
        return true;
    })()`);
    assert(Boolean(await waitFor(`document.querySelector('[data-travel-hero-intro]')?.classList.contains('swiper-slide-active')`)), 'Informational hero is not the first slide');
    const home = await evaluate(`(() => ({
        tours: document.querySelectorAll('.travel-tour-card').length,
        destinations: document.querySelectorAll('.travel-destination-card').length,
        firstTour: document.querySelector('.travel-tour-card h3 a')?.href || '',
        centeredBrand: Math.abs(document.querySelector('.travel-primary-nav .travel-brand').getBoundingClientRect().left + document.querySelector('.travel-primary-nav .travel-brand').getBoundingClientRect().width / 2 - innerWidth / 2) < 3,
        accountButtonColor: getComputedStyle(document.querySelector('.travel-primary-nav__tools .travel-button')).color,
        heroTourSlides: document.querySelectorAll('[data-travel-hero-tour]').length,
        heroDots: document.querySelectorAll('[data-travel-hero-pagination] .swiper-pagination-bullet').length,
        introActive: document.querySelector('[data-travel-hero-intro]')?.classList.contains('swiper-slide-active') || false,
        heroControls: document.querySelectorAll('[data-travel-hero-prev], [data-travel-hero-next]').length,
        heroPaginationCentered: (() => { const box = document.querySelector('[data-travel-hero-pagination]')?.getBoundingClientRect(); return Boolean(box && Math.abs(box.left + box.width / 2 - innerWidth / 2) <= 2); })(),
        heroPaginationGeometry: (() => { const node = document.querySelector('[data-travel-hero-pagination]'); const box = node?.getBoundingClientRect(); const style = node ? getComputedStyle(node) : null; return { className: node?.className || '', inline: node?.getAttribute('style') || '', left: box?.left, width: box?.width, cssLeft: style?.left, cssRight: style?.right, transform: style?.transform }; })(),
    }))()`);
    diagnostics.push({ name: 'home-content', ...home });
    assert(home.tours === 6 && home.destinations === 6, 'Homepage does not expose all six demonstration journeys and destinations');
    assert(home.firstTour && home.centeredBrand, 'Homepage tour discovery or centered desktop logo is incomplete');
    assert(home.accountButtonColor === 'rgb(255, 255, 255)', 'Desktop account button does not retain readable contrast');
    assert(home.heroTourSlides === 6 && home.heroDots === 7 && home.introActive, 'Hero slide count, pagination, or informational-first order is incorrect');
    assert(home.heroControls === 2, 'Hero previous and next controls are incomplete');
    assert(home.heroPaginationCentered, 'Hero pagination is not centered in the viewport');
    await screenshot('desktop-light-home');

    await evaluate(`document.querySelector('[data-travel-hero-next]')?.click()`);
    assert(Boolean(await waitFor(`document.querySelector('[data-travel-hero-slider]')?.swiper?.realIndex === 1`)), 'Hero next arrow did not reveal the first tour slide');
    await delay(850);
    await evaluate(`document.querySelector('[data-travel-hero-next]')?.click()`);
    assert(Boolean(await waitFor(`document.querySelector('[data-travel-hero-slider]')?.swiper?.realIndex === 2`)), 'Hero next arrow stopped responding after its first use');
    await delay(850);
    await evaluate(`document.querySelector('[data-travel-hero-prev]')?.click()`);
    assert(Boolean(await waitFor(`document.querySelector('[data-travel-hero-slider]')?.swiper?.realIndex === 1`)), 'Hero previous arrow did not return to the first tour slide');
    await delay(850);
    await evaluate(`document.querySelectorAll('[data-travel-hero-pagination] .swiper-pagination-bullet')[4]?.click()`);
    assert(Boolean(await waitFor(`document.querySelector('[data-travel-hero-slider]')?.swiper?.realIndex === 4`)), 'Hero pagination did not navigate to a nonadjacent slide');
    await delay(850);
    await evaluate(`document.querySelectorAll('[data-travel-hero-pagination] .swiper-pagination-bullet')[0]?.click()`);
    assert(Boolean(await waitFor(`document.querySelector('[data-travel-hero-slider]')?.swiper?.realIndex === 0`)), 'Hero pagination stopped responding after its first use');
    await delay(850);
    await evaluate(`document.querySelector('[data-travel-hero-next]')?.click()`);
    assert(Boolean(await waitFor(`document.querySelector('[data-travel-hero-slider]')?.swiper?.realIndex === 1`)), 'Hero next arrow did not recover after pagination navigation');
    await delay(850);
    const heroTour = await evaluate(`(() => {
        const slide = document.querySelector('[data-travel-hero-tour].swiper-slide-active');
        const slider = document.querySelector('[data-travel-hero-slider]')?.swiper;
        return {
            title: slide?.querySelector('h2')?.textContent.trim() || '',
            ctas: slide?.querySelectorAll('.travel-hero__actions a').length || 0,
            realIndex: slider?.realIndex,
            activeBullet: [...document.querySelectorAll('[data-travel-hero-pagination] .swiper-pagination-bullet')].findIndex((bullet) => bullet.classList.contains('swiper-pagination-bullet-active')),
        };
    })()`);
    diagnostics.push({ name: 'home-hero-tour-slide', ...heroTour });
    assert(heroTour.title && heroTour.ctas >= 1 && heroTour.realIndex === 1 && heroTour.activeBullet === 1, 'Active tour hero or synchronized pagination state is incomplete');
    await screenshot('desktop-light-home-tour-slide');

    await evaluate(`document.querySelector('.travel-primary-nav button[data-bs-target="#themeController"]')?.click()`);
    assert(Boolean(await waitFor(`document.querySelector('#themeController.show')`)), 'Theme controller did not open from the desktop header');
    await screenshot('desktop-light-theme-controller');
    await evaluate(`document.querySelector('#themeController [data-bs-dismiss="offcanvas"]')?.click()`);
    await waitFor(`!document.querySelector('#themeController.show')`);

    await navigate(`${siteUrl}/tours`);
    await setTheme('dark');
    const catalogDark = await inspect('desktop-dark-catalog', '/tours', 'dark', 'desktop');
    const catalog = await evaluate(`(() => ({
        count: document.querySelectorAll('.travel-tour-card').length,
        grid: getComputedStyle(document.querySelector('.travel-tour-grid')).display,
        prices: [...document.querySelectorAll('.travel-tour-card__price')].map((node) => node.textContent.trim()),
        filters: document.querySelectorAll('.travel-filter input, .travel-filter select').length,
        total: document.querySelector('#travel-results h2')?.textContent.trim() || '',
    }))()`);
    diagnostics.push({ name: 'catalog-content', ...catalog });
    assert(catalog.count === 12 && catalog.total === '13 journeys' && catalog.grid === 'grid', 'Catalog pagination, cards, or module stylesheet failed');
    assert(catalog.filters === 6 && catalog.prices.every((price) => price.startsWith('From KES ')), 'Catalog filters or formatted prices are incomplete');
    assert(homeLight.surface !== catalogDark.surface, 'Light and dark theme surfaces did not change');
    await screenshot('desktop-dark-catalog');

    const detailPath = new URL(home.firstTour).pathname;
    await navigate(home.firstTour);
    await setTheme('light');
    await inspect('desktop-light-tour-detail', detailPath, 'light', 'desktop');
    const detail = await evaluate(`(() => ({
        departures: document.querySelectorAll('.travel-departure-card').length,
        itinerary: document.querySelectorAll('.travel-itinerary details').length,
        faqs: document.querySelectorAll('.travel-faqs details').length,
        inquiryControls: document.querySelectorAll('.travel-inquiry input:not([type="hidden"]), .travel-inquiry select, .travel-inquiry textarea').length,
        productSchema: [...document.querySelectorAll('script[type="application/ld+json"]')].some((node) => node.textContent.includes('TouristTrip')),
    }))()`);
    diagnostics.push({ name: 'tour-detail-content', ...detail });
    assert(detail.departures === 2 && detail.itinerary >= 4 && detail.faqs === 2, 'Tour detail lacks departures, itinerary, or FAQs');
    assert(detail.inquiryControls >= 5 && detail.productSchema, 'Inquiry controls or TouristTrip schema is incomplete');
    await screenshot('desktop-light-tour-detail');
    if (process.env.AUREON_QA_PHASE === 'k2e') {
        assert(Boolean(await waitFor('document.querySelector(\'[data-tour-gallery][data-gallery-ready="true"]\')')), 'Tour gallery did not initialize');
        await evaluate('document.querySelector("[data-tour-gallery-expand]")?.focus(); document.querySelector("[data-tour-gallery-expand]")?.click()');
        const expanded = await evaluate('(() => { const box = document.querySelector("[data-tour-lightbox]"); const close = box?.querySelector(".travel-tour-lightbox__close"); return { open: Boolean(box && !box.hidden), closeFocused: document.activeElement === close, imageVisible: Boolean(box?.querySelector(".swiper-slide-active img")?.naturalWidth), caption: box?.querySelector(".swiper-slide-active figcaption")?.textContent.trim() || "", controls: box?.querySelectorAll("[data-tour-lightbox-prev], [data-tour-lightbox-next]").length, count: box?.querySelector("[data-tour-lightbox-count]")?.textContent.trim() || "" }; })()');
        diagnostics.push({ name: 'tour-gallery-expanded', ...expanded });
        assert(expanded.open && expanded.closeFocused && expanded.imageVisible, 'Full-screen tour gallery did not open with focus and a visible image');
        assert(expanded.caption && expanded.controls === 2 && expanded.count.includes('/'), 'Expanded gallery is missing captions or navigation controls');
        await screenshot('desktop-light-tour-gallery-expanded');
        await evaluate('document.querySelector("[data-tour-lightbox-next]")?.click()');
        assert(Boolean(await waitFor('document.querySelector("[data-tour-lightbox-count]")?.textContent.trim() === "2 / 2"')), 'Expanded gallery next arrow did not navigate');
        const nextCaption = await evaluate('document.querySelector("[data-tour-lightbox] .swiper-slide-active figcaption")?.textContent.trim()');
        assert(nextCaption.includes('Historic pilgrimage destination'), 'Expanded gallery did not update the image title');
        await evaluate('document.activeElement.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }))');
        assert(Boolean(await waitFor('document.querySelector("[data-tour-lightbox]")?.hidden')), 'Gallery close control did not dismiss the overlay');
        assert(Boolean(await evaluate('document.activeElement?.matches("[data-tour-gallery-expand]")')), 'Gallery did not restore focus to its opener');
    }

    await setViewport(820, 1180);
    await navigate(`${siteUrl}/tours?destination=egypt`);
    await setTheme('dark');
    await inspect('tablet-dark-filtered-catalog', '/tours', 'dark', 'tablet');
    const filtered = await evaluate(`(() => ({ count: document.querySelectorAll('.travel-tour-card').length, title: document.querySelector('.travel-tour-card h3')?.textContent.trim() }))()`);
    diagnostics.push({ name: 'filtered-catalog', ...filtered });
    assert(filtered.count === 1 && filtered.title.includes('Cairo'), 'Destination filter did not isolate the expected tour');
    await screenshot('tablet-dark-filtered-catalog');

    await setViewport(390, 844, true);
    await client.send('Emulation.setEmulatedMedia', { media: 'screen', features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await navigate(`${siteUrl}/`);
    await setTheme('light');
    await inspect('mobile-light-home', '/', 'light', 'mobile');
    assert(Boolean(await waitFor(`Boolean(document.querySelector('[data-travel-hero-slider][data-hero-ready="true"]'))`)), 'Reduced-motion mobile hero did not initialize');
    const reducedHero = await evaluate(`(() => { const swiper = document.querySelector('[data-travel-hero-slider]')?.swiper; return { speed: swiper?.params.speed, autoplay: Boolean(swiper?.autoplay?.running) }; })()`);
    diagnostics.push({ name: 'mobile-reduced-motion-hero', ...reducedHero });
    assert(reducedHero.speed === 0 && !reducedHero.autoplay, 'Reduced-motion hero still animates or autoplays');
    const mobileHeroPagination = await evaluate(`(() => {
        const hero = document.querySelector('[data-travel-hero-slider]')?.getBoundingClientRect();
        const pagination = document.querySelector('[data-travel-hero-pagination]')?.getBoundingClientRect();
        return {
            centered: Boolean(pagination && Math.abs(pagination.left + pagination.width / 2 - innerWidth / 2) <= 2),
            bottomGap: hero && pagination ? Math.round(hero.bottom - pagination.bottom) : null,
        };
    })()`);
    diagnostics.push({ name: 'mobile-hero-pagination', ...mobileHeroPagination });
    assert(mobileHeroPagination.centered && mobileHeroPagination.bottomGap >= 30, 'Mobile hero pagination is not centered and raised above the hero edge');
    await screenshot('mobile-light-home');
    await evaluate(`document.querySelector('.travel-header__mobile button[aria-label="Open navigation"]')?.click()`);
    assert(Boolean(await waitFor(`document.querySelector('#travelMobileMenu.show')`)), 'Mobile navigation did not open');
    const mobileMenu = await evaluate(`(() => {
        const menu = document.querySelector('#travelMobileMenu');
        const header = menu.querySelector('.offcanvas-header').getBoundingClientRect();
        const close = menu.querySelector('[aria-label="Close navigation"]').getBoundingClientRect();
        return {
            closeRightGap: Math.round(header.right - close.right),
            overflow: menu.scrollWidth > menu.clientWidth + 1,
            workspace: Boolean(menu.querySelector('a[href$="/login"]')),
            signup: Boolean(menu.querySelector('a[href$="/register"]')),
            reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
            workspaceColor: getComputedStyle(menu.querySelector('.travel-mobile-menu__actions .travel-button')).color,
        };
    })()`);
    diagnostics.push({ name: 'mobile-menu', ...mobileMenu });
    assert(mobileMenu.closeRightGap <= 28 && !mobileMenu.overflow, 'Mobile menu close control or width is incorrect');
    assert(mobileMenu.workspace && mobileMenu.signup && mobileMenu.reducedMotion, 'Mobile account actions or reduced-motion mode is incomplete');
    assert(mobileMenu.workspaceColor === 'rgb(255, 255, 255)', 'Mobile workspace button does not retain readable contrast');
    await screenshot('mobile-light-navigation');

    await evaluate(`document.querySelector('#travelMobileMenu [data-bs-dismiss="offcanvas"]')?.click()`);
    await setViewport(320, 700, true);
    await navigate(home.firstTour);
    await setTheme('dark');
    await inspect('narrow-dark-tour-detail', detailPath, 'dark', 'mobile');
    await screenshot('narrow-dark-tour-detail');
    if (process.env.AUREON_QA_PHASE === 'k2e') {
        assert(Boolean(await waitFor('document.querySelector(\'[data-tour-gallery][data-gallery-ready="true"]\')')), 'Mobile tour gallery did not initialize');
        await evaluate('document.querySelector("[data-tour-gallery-expand]")?.click()');
        assert(Boolean(await evaluate('!document.querySelector("[data-tour-lightbox]")?.hidden')), 'Mobile gallery did not expand');
        await screenshot('narrow-dark-tour-gallery-expanded');
        await evaluate('document.querySelector(".travel-tour-lightbox__close")?.click()');
    }

    assert(runtimeErrors.length === 0, `Runtime errors: ${runtimeErrors.join(' | ')}`);
    assert(networkErrors.length === 0, `Network errors: ${networkErrors.join(' | ')}`);
    await writeFile(path.join(outputDirectory, 'diagnostics.json'), `${JSON.stringify({ siteUrl, diagnostics, runtimeErrors, networkErrors, failures }, null, 2)}\n`);
    if (failures.length > 0) throw new Error(`TravelTours storefront QA failed:\n- ${failures.join('\n- ')}`);
    process.stdout.write(`TravelTours storefront QA passed with ${diagnostics.length} inspections and ${process.env.AUREON_QA_PHASE === 'k2e' ? 11 : 9} viewport captures.\n`);
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
