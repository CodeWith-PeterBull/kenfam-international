(() => {
    'use strict';

    const focusLookup = () => {
        const lookup = document.querySelector('#pos-product-search');
        if (lookup instanceof HTMLInputElement && window.innerWidth >= 768) {
            lookup.focus({ preventScroll: true });
        }
    };

    // Cashier add-to-cart beep, synthesised with the Web Audio API so it needs
    // no audio asset. The server dispatches `commerce-pos-cart-added` only when
    // the sound is enabled and an add actually succeeds; this side just plays it.
    let audioContext = null;
    let audioUnlockBound = false;

    const ensureAudioContext = () => {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return null;
        if (!audioContext) {
            try {
                audioContext = new AudioContextClass();
            } catch {
                return null;
            }
        }
        if (audioContext.state === 'suspended') {
            audioContext.resume().catch(() => {});
        }
        return audioContext;
    };

    // Browsers block audio until a user gesture; the terminal is click-heavy, so
    // resume the context on the first interaction to unlock even the first beep.
    const bindAudioUnlock = () => {
        if (audioUnlockBound) return;
        audioUnlockBound = true;
        const unlock = () => ensureAudioContext();
        window.addEventListener('pointerdown', unlock, { passive: true });
        window.addEventListener('keydown', unlock, { passive: true });
    };

    const playAddToCartBeep = () => {
        const context = ensureAudioContext();
        if (!context) return;
        try {
            const now = context.currentTime;
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, now);
            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(0.18, now + 0.012);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.14);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start(now);
            oscillator.stop(now + 0.16);
        } catch {
            // Sound is a non-critical enhancement; ignore playback failures.
        }
    };

    const initialize = () => {
        document.querySelectorAll('[data-pos-print]').forEach((button) => {
            if (button.dataset.posPrintReady === 'true') return;
            button.dataset.posPrintReady = 'true';
            button.addEventListener('click', () => requestReceiptPrint('manual'));
        });

        initializeAutomaticReceiptPrint();
    };

    const receiptInstruction = () => {
        const element = document.querySelector('[data-pos-print-instruction]');
        if (!(element instanceof HTMLScriptElement)) return null;

        try {
            return JSON.parse(element.textContent || '{}');
        } catch {
            return null;
        }
    };

    const requestReceiptPrint = (reason) => {
        const instruction = receiptInstruction();
        if (!instruction) return;

        const event = new CustomEvent('commerce:receipt-print', {
            bubbles: true,
            cancelable: true,
            detail: { ...instruction, reason },
        });

        if (window.dispatchEvent(event)) {
            window.print();
        }
    };

    const initializeAutomaticReceiptPrint = () => {
        const receipt = document.querySelector('[data-pos-receipt]');
        const instruction = receiptInstruction();
        if (!(receipt instanceof HTMLElement) || !instruction?.autoPrompt) return;
        if (receipt.dataset.posAutoPrintReady === 'true') return;
        receipt.dataset.posAutoPrintReady = 'true';

        const storageKey = `commerce-pos-receipt-print:${instruction.receiptKey}`;
        try {
            if (window.sessionStorage.getItem(storageKey) === 'prompted') return;
        } catch {
            // Printing remains available when browser storage is unavailable.
        }

        let promptScheduled = false;
        const prompt = () => {
            if (promptScheduled) return;
            promptScheduled = true;
            window.setTimeout(() => {
                try {
                    window.sessionStorage.setItem(storageKey, 'prompted');
                } catch {
                    // The print request does not depend on browser storage.
                }
                requestReceiptPrint('checkout');
            }, 250);
        };
        const promptAfterLoader = () => {
            const loader = document.querySelector('[data-page-loader]');
            if (loader instanceof HTMLElement && !loader.hidden) {
                window.addEventListener('aureon:page-loader-dismissed', prompt, { once: true });
                window.setTimeout(prompt, 7000);
                return;
            }
            prompt();
        };

        if (document.readyState === 'complete') promptAfterLoader();
        else window.addEventListener('load', promptAfterLoader, { once: true });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initialize();
        focusLookup();
    });
    document.addEventListener('livewire:navigated', initialize);
    document.addEventListener('livewire:init', () => {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;
        window.Livewire.hook('commit', ({ succeed }) => succeed(() => initialize()));
        bindAudioUnlock();
        if (typeof window.Livewire.on === 'function') {
            window.Livewire.on('commerce-pos-cart-added', playAddToCartBeep);
        }
    });
})();
