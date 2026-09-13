/**
 * Authenticated + guest browser QA for the rebranded auth screens and the
 * email-OTP two-factor flow. Sibling of scripts/qa-dashboard.mjs (same CDP /
 * headless-Chromium harness); evidence lands in .docs/dev/auth-qa/.
 *
 * Prerequisites: `npm.cmd run build` and a Laravel server on AUREON_QA_URL
 * (default http://127.0.0.1:8012).
 *
 * Default mode captures: login (desktop / mobile / dark), register,
 * forgot-password, confirm-password, and the profile Security panel.
 *
 * Two-factor mode (AUREON_QA_2FA=1) additionally drives the full challenge:
 * requires the server to run with TWO_FACTOR_ENABLED=true. The script opts
 * the QA user in via `php artisan tinker`, captures the challenge screens,
 * then plants a KNOWN code digest (tinker, real HMAC path) and completes the
 * login — deterministic, no mail-log scraping — and finally restores the
 * user's 2FA state.
 */
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { spawn, spawnSync } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const outputDirectory = path.join(root, '.docs', 'dev', 'auth-qa');
const siteUrl = process.env.AUREON_QA_URL || 'http://127.0.0.1:8012';
const email = process.env.AUREON_QA_EMAIL || 'admin@aureon.test';
const password = process.env.AUREON_QA_PASSWORD || 'password';
const twoFactorMode = process.env.AUREON_QA_2FA === '1';
const debuggingPort = Number(process.env.AUREON_QA_PORT || 9453);
const knownOtp = '135790';
const browserPath = [
    'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find(existsSync);

if (!browserPath) throw new Error('No supported Chromium browser was found');

/**
 * Run a PHP snippet inside the application via artisan tinker. Used to
 * arrange and restore the QA user's 2FA state without touching the mailer.
 */
function tinker(code) {
    const result = spawnSync('php', ['artisan', 'tinker', `--execute=${code}`], {
        cwd: root,
        encoding: 'utf8',
    });
    if (result.status !== 0) {
        throw new Error(`tinker failed: ${result.stderr || result.stdout}`);
    }
    return result.stdout;
}

await mkdir(outputDirectory, { recursive: true });
const profileDirectory = await mkdtemp(path.join(os.tmpdir(), 'aureon-auth-qa-'));
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

    // php artisan serve is single-threaded; cold pages with many static
    // assets can comfortably exceed 15s, so allow a generous window.
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
let userStateArranged = false;
const runtimeErrors = [];
const networkErrors = [];
const diagnostics = [];
const failures = [];

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
        if (event.response.status >= 400 && event.response.status !== 429) {
            networkErrors.push(`${event.response.status} ${event.response.url}`);
        }
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

    /** Snapshot the current page and collect layout/branding diagnostics. */
    async function capture(name) {
        diagnostics.push(await evaluate(`(() => {
            const logo = document.querySelector('.login-logo img, .sidebar-logo img');
            return {
                name: ${JSON.stringify(name)},
                url: location.pathname,
                viewportWidth: innerWidth,
                documentWidth: document.documentElement.scrollWidth,
                theme: document.documentElement.dataset.bsTheme,
                logoReady: Boolean(logo && logo.complete && logo.naturalWidth > 0),
            };
        })()`));

        const screenshot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true,
            captureBeyondViewport: false,
        });
        await writeFile(path.join(outputDirectory, `${name}.png`), Buffer.from(screenshot.data, 'base64'));
    }

    /** Fill and submit the login form on the current /login page. */
    async function submitLogin() {
        const landed = client.once('Page.loadEventFired');
        await evaluate(`(() => {
            const email = document.querySelector('input[name="email"]');
            const password = document.querySelector('input[name="password"]');
            email.value = ${JSON.stringify(email)};
            password.value = ${JSON.stringify(password)};
            email.dispatchEvent(new Event('input', { bubbles: true }));
            password.dispatchEvent(new Event('input', { bubbles: true }));
            document.querySelector('form').requestSubmit();
        })()`);
        await landed;
        await delay(150);
        return evaluate('location.pathname');
    }

    // ------------------------------------------------------------------
    // Arrange: in two-factor mode, opt the QA user in before any login.
    // ------------------------------------------------------------------
    if (twoFactorMode) {
        tinker(`App\\Models\\User::where('email', '${email}')->firstOrFail()->forceFill(['two_factor_enabled' => true])->save();`);
        userStateArranged = true;
    }

    // ------------------------------------------------------------------
    // Guest screens: login (light desktop / dark / mobile), register,
    // forgot password. Appearance follows the persisted dashboard theme.
    // ------------------------------------------------------------------
    await setViewport(1440, 1000, false);
    await navigate(`${siteUrl}/login`);
    await evaluate("localStorage.removeItem('laravel-aureon-dashboard-settings'); localStorage.removeItem('laravel-aureon-dashboard-theme')");
    await navigate(`${siteUrl}/login`);
    await capture('login-desktop');

    // Password eye toggle must reveal the field (vanilla aureon-auth.js).
    const eyeToggle = await evaluate(`(() => {
        const input = document.querySelector('.pass-group .pass-input');
        const toggle = document.querySelector('.pass-group .toggle-password');
        if (!input || !toggle) return { present: false };
        toggle.click();
        const revealed = input.type === 'text';
        toggle.click();
        return { present: true, revealed, restored: input.type === 'password' };
    })()`);

    await evaluate(`localStorage.setItem('laravel-aureon-dashboard-settings', JSON.stringify({ mode: 'dark' }))`);
    await navigate(`${siteUrl}/login`);
    await capture('login-dark');
    await evaluate("localStorage.removeItem('laravel-aureon-dashboard-settings'); localStorage.removeItem('laravel-aureon-dashboard-theme')");

    await setViewport(390, 844, true);
    await navigate(`${siteUrl}/login`);
    await capture('login-mobile');

    await setViewport(1440, 1000, false);
    await navigate(`${siteUrl}/register`);
    await capture('register-desktop');
    await navigate(`${siteUrl}/forgot-password`);
    await capture('forgot-password-desktop');

    // ------------------------------------------------------------------
    // Login. In two-factor mode this must land on the challenge screen;
    // otherwise it goes straight to the dashboard.
    // ------------------------------------------------------------------
    await navigate(`${siteUrl}/login`);
    let landedPath = await submitLogin();
    let challenge = { attempted: twoFactorMode, landedPath };

    if (twoFactorMode) {
        if (!landedPath.includes('two-factor/challenge')) {
            failures.push(`two-factor mode: expected the challenge, landed on ${landedPath} (is the server running with TWO_FACTOR_ENABLED=true?)`);
        } else {
            await capture('two-factor-challenge-desktop');
            challenge.maskedEmailShown = await evaluate("document.body.textContent.includes('***@')");
            challenge.otpInputReady = await evaluate("Boolean(document.querySelector('[data-otp-input]'))");

            await setViewport(390, 844, true);
            await navigate(`${siteUrl}/two-factor/challenge`);
            await capture('two-factor-challenge-mobile');
            await setViewport(1440, 1000, false);
            await navigate(`${siteUrl}/two-factor/challenge`);

            // Plant a known digest through the real HMAC path, then verify
            // through the real controller — deterministic, no mail scraping.
            tinker(
                `App\\Models\\User::where('email', '${email}')->firstOrFail()->forceFill([`
                + `'two_factor_code_hash' => hash_hmac('sha256', '${knownOtp}', (string) config('app.key')),`
                + `'two_factor_code_expires_at' => now()->addMinutes(10),`
                + `])->save();`
            );

            const verified = client.once('Page.loadEventFired');
            await evaluate(`(() => {
                const code = document.querySelector('[data-otp-input]');
                code.value = ${JSON.stringify(knownOtp)};
                code.dispatchEvent(new Event('input', { bubbles: true }));
                code.closest('form').requestSubmit();
            })()`);
            await verified;
            await delay(150);
            challenge.verifiedPath = await evaluate('location.pathname');
            await capture('two-factor-success');
            if (!challenge.verifiedPath.includes('dashboard')) {
                failures.push(`two-factor verification did not reach the dashboard (landed on ${challenge.verifiedPath})`);
            }
            landedPath = challenge.verifiedPath;
        }
    } else if (!landedPath.includes('dashboard')) {
        failures.push(`login did not reach the dashboard (landed on ${landedPath})`);
    }

    // ------------------------------------------------------------------
    // Authenticated screens: confirm-password gate + profile Security card.
    // ------------------------------------------------------------------
    if (landedPath.includes('dashboard')) {
        await navigate(`${siteUrl}/confirm-password`);
        await capture('confirm-password-desktop');

        await navigate(`${siteUrl}/profile`);
        await capture('profile-security-desktop');
        const profile = await evaluate(`(() => ({
            securityCard: document.body.textContent.includes('Security — two-factor authentication'),
            livewireReady: [...document.querySelectorAll('*')].some((element) => element.hasAttribute('wire:id')),
            dangerZone: document.body.textContent.includes('Danger zone'),
        }))()`);
        if (!profile.securityCard) failures.push('profile: Security card is missing');
        if (!profile.livewireReady) failures.push('profile: Livewire security panel did not initialize');
        if (!profile.dangerZone) failures.push('profile: danger zone card is missing');
        diagnostics.push({ name: 'profile-details', ...profile });
    }

    // ------------------------------------------------------------------
    // Assertions over collected diagnostics.
    // ------------------------------------------------------------------
    for (const result of diagnostics) {
        if (result.documentWidth && result.documentWidth > result.viewportWidth + 1) {
            failures.push(`${result.name}: horizontal overflow (${result.documentWidth} > ${result.viewportWidth})`);
        }
        if (result.logoReady === false) failures.push(`${result.name}: brand logo did not render`);
    }
    const darkShot = diagnostics.find((entry) => entry.name === 'login-dark');
    if (darkShot && darkShot.theme !== 'dark') failures.push('login-dark: dark theme attribute was not applied');
    if (!eyeToggle.present) failures.push('login: password eye toggle is missing');
    else if (!eyeToggle.revealed || !eyeToggle.restored) failures.push('login: password eye toggle did not toggle the field');
    if (twoFactorMode && challenge.otpInputReady === false) failures.push('challenge: OTP input is missing');
    if (twoFactorMode && challenge.maskedEmailShown === false) failures.push('challenge: masked email is not shown');
    failures.push(...runtimeErrors, ...networkErrors);

    await writeFile(
        path.join(outputDirectory, 'diagnostics.json'),
        `${JSON.stringify({ twoFactorMode, diagnostics, eyeToggle, challenge, runtimeErrors, networkErrors, failures }, null, 2)}\n`,
    );

    if (failures.length) throw new Error(failures.join('\n'));
    process.stdout.write(`${JSON.stringify({ twoFactorMode, diagnostics, eyeToggle, challenge }, null, 2)}\n`);
} finally {
    // Restore the QA user's 2FA state and clear any planted challenge.
    if (userStateArranged) {
        try {
            tinker(`App\\Models\\User::where('email', '${email}')->firstOrFail()->forceFill(['two_factor_enabled' => false, 'two_factor_code_hash' => null, 'two_factor_code_expires_at' => null])->save();`);
        } catch (error) {
            process.stderr.write(`WARNING: failed to restore QA user 2FA state: ${error.message}\n`);
        }
    }
    client?.close();
    browser.kill();
    await Promise.race([
        new Promise((resolve) => browser.once('exit', resolve)),
        delay(1200),
    ]);
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
