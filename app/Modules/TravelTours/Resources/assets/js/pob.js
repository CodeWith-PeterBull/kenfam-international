/**
 * Travel booking desk (Point of Booking) receipt printing.
 *
 * The receipt page embeds the register's print instruction as JSON. Manual
 * print buttons open the browser dialog on demand; when the register asks
 * for it, the dialog opens once after checkout and is remembered per receipt
 * so a reload never prints twice. Mirrors the PropertyBooking POB script.
 */
(() => {
    'use strict';

    const instruction = () => {
        const node = document.querySelector('[data-pob-print-instruction]');
        if (!(node instanceof HTMLScriptElement)) return null;

        try {
            return JSON.parse(node.textContent || '{}');
        } catch {
            return null;
        }
    };

    const printReceipt = (reason) => {
        const settings = instruction();
        if (!settings) return;
        const event = new CustomEvent('travel-tours:receipt-print', {
            bubbles: true,
            cancelable: true,
            detail: { ...settings, reason },
        });
        if (window.dispatchEvent(event)) window.print();
    };

    const initialize = () => {
        document.querySelectorAll('[data-pob-print]').forEach((button) => {
            if (button.dataset.pobPrintReady === 'true') return;
            button.dataset.pobPrintReady = 'true';
            button.addEventListener('click', () => printReceipt('manual'));
        });

        const receipt = document.querySelector('[data-pob-receipt]');
        const settings = instruction();
        if (!(receipt instanceof HTMLElement) || !settings?.autoPrompt || receipt.dataset.pobAutoPrintReady === 'true') return;
        receipt.dataset.pobAutoPrintReady = 'true';
        const key = `travel-tours-receipt-print:${settings.receiptKey}`;
        try {
            if (window.sessionStorage.getItem(key) === 'prompted') return;
            window.sessionStorage.setItem(key, 'prompted');
        } catch {
            // Printing remains available when browser storage is unavailable.
        }
        window.setTimeout(() => printReceipt('checkout'), 350);
    };

    document.addEventListener('DOMContentLoaded', initialize);
    document.addEventListener('livewire:navigated', initialize);
    document.addEventListener('livewire:init', () => {
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('commit', ({ succeed }) => succeed(initialize));
        }
    });
})();
