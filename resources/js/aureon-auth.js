/**
 * Aureon auth-screen behaviours.
 *
 * Vanilla replacements for the two DreamPOS interactions auth pages need,
 * so the screens can skip jQuery and the full dashboard script bundle:
 *
 *  1. Password visibility toggling for `.pass-group` fields (the DreamPOS
 *     markup contract: an `.toggle-password` icon beside a `.pass-input`).
 *  2. Numeric-only filtering for the one-time-code field (`[data-otp-input]`)
 *     so pasted or typed input is coerced to at most `maxlength` digits.
 */
(() => {
    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    ready(() => {
        // 1. Password eye toggles.
        document.querySelectorAll('.pass-group .toggle-password').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const input = toggle.closest('.pass-group')?.querySelector('.pass-input');
                if (!input) return;

                const reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                toggle.classList.toggle('ti-eye', reveal);
                toggle.classList.toggle('ti-eye-off', !reveal);
            });
        });

        // 2. OTP input hygiene.
        document.querySelectorAll('[data-otp-input]').forEach((input) => {
            const limit = Number(input.getAttribute('maxlength')) || 6;

            input.addEventListener('input', () => {
                const digits = input.value.replace(/\D+/g, '').slice(0, limit);
                if (input.value !== digits) input.value = digits;
            });
        });
    });
})();
