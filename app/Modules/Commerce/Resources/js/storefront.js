(function () {
    'use strict';

    function createIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({ attrs: { 'stroke-width': 1.8 } });
        }
    }

    function setCurrentYear() {
        document.querySelectorAll('[data-current-year]').forEach((element) => {
            element.textContent = String(new Date().getFullYear());
        });
    }

    function initializeBackToTop() {
        const button = document.querySelector('[data-commerce-back-to-top]');
        if (!button || button.dataset.initialized === 'true') return;

        button.dataset.initialized = 'true';
        const update = () => button.classList.toggle('visible', window.scrollY > 600);
        window.addEventListener('scroll', update, { passive: true });
        button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        update();
    }

    function initializeProductGalleries() {
        const galleries = document.querySelectorAll('[data-product-gallery]:not([data-gallery-ready="true"])');
        if (galleries.length === 0) return;

        // Swiper (arrows, dots, thumb sync, full-screen lightbox) is code-split
        // into its own chunk and only fetched on pages that carry a gallery.
        import('./product-gallery.js')
            .then(({ initProductGallery }) => galleries.forEach((gallery) => initProductGallery(gallery)))
            .catch(() => { /* Slider is a progressive enhancement; the first image still renders. */ });
    }

    function showShareToast(message) {
        let toast = document.querySelector('[data-commerce-toast]');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'commerce-toast';
            toast.setAttribute('data-commerce-toast', '');
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('visible');
        window.clearTimeout(toast.dataset.timer);
        toast.dataset.timer = String(window.setTimeout(() => toast.classList.remove('visible'), 2600));
    }

    async function copyLink(url) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(url);
                return true;
            }
        } catch (error) {
            // Fall through to the legacy path below.
        }
        try {
            const field = document.createElement('textarea');
            field.value = url;
            field.setAttribute('readonly', '');
            field.style.position = 'absolute';
            field.style.left = '-9999px';
            document.body.appendChild(field);
            field.select();
            const copied = document.execCommand('copy');
            document.body.removeChild(field);
            return copied;
        } catch (error) {
            return false;
        }
    }

    function initializeShare() {
        document.querySelectorAll('[data-share-copy]').forEach((button) => {
            if (button.dataset.shareBound === 'true') return;
            button.dataset.shareBound = 'true';
            button.addEventListener('click', async () => {
                const url = button.getAttribute('data-share-copy');
                if (!url) return;
                const hint = button.getAttribute('data-share-hint');
                const ok = await copyLink(url);
                showShareToast(ok
                    ? (hint ? `Link copied — paste into ${hint}` : 'Product link copied')
                    : 'Copy failed — copy the address bar link');
            });
        });

        document.querySelectorAll('[data-share-native]').forEach((button) => {
            if (button.dataset.shareBound === 'true') return;
            if (typeof navigator.share !== 'function') return;
            button.dataset.shareBound = 'true';
            button.hidden = false;
            button.addEventListener('click', async () => {
                try {
                    await navigator.share({
                        title: button.getAttribute('data-share-title') || document.title,
                        url: button.getAttribute('data-share-url') || window.location.href,
                    });
                } catch (error) {
                    // The user dismissed the native share sheet; nothing to do.
                }
            });
        });

        document.querySelectorAll('[data-share-window]').forEach((anchor) => {
            if (anchor.dataset.shareBound === 'true') return;
            anchor.dataset.shareBound = 'true';
            anchor.addEventListener('click', (event) => {
                event.preventDefault();
                window.open(anchor.href, 'commerce-share', 'noopener,noreferrer,width=640,height=680');
            });
        });
    }

    function initialize() {
        createIcons();
        setCurrentYear();
        initializeBackToTop();
        initializeProductGalleries();
        initializeShare();
    }

    document.addEventListener('DOMContentLoaded', initialize);
    document.addEventListener('livewire:init', () => {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;
        window.Livewire.hook('morph.updated', () => createIcons());
    });
    document.addEventListener('livewire:navigated', initialize);
}());
