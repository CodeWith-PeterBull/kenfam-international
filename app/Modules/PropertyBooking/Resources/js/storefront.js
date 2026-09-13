(function () {
    'use strict';

    function createIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({ attrs: { 'stroke-width': 1.8 } });
        }
    }

    function initializeGalleries() {
        const galleries = document.querySelectorAll('[data-stay-gallery]:not([data-gallery-ready="true"])');
        if (galleries.length === 0) return;

        import('./stay-gallery.js')
            .then(({ initStayGallery }) => galleries.forEach((gallery) => initStayGallery(gallery)))
            .catch(() => { /* The first image remains available without the enhancement. */ });
    }

    function initializeBackToTop() {
        const button = document.querySelector('[data-pb-back-to-top]');
        if (!button || button.dataset.initialized === 'true') return;

        button.dataset.initialized = 'true';
        const update = () => button.classList.toggle('visible', window.scrollY > 600);
        window.addEventListener('scroll', update, { passive: true });
        button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        update();
    }

    function initialize() {
        createIcons();
        document.querySelectorAll('[data-current-year]').forEach((element) => {
            element.textContent = String(new Date().getFullYear());
        });
        initializeGalleries();
        initializeBackToTop();
    }

    document.addEventListener('DOMContentLoaded', initialize);
    document.addEventListener('livewire:init', () => {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;
        window.Livewire.hook('morph.updated', () => createIcons());
    });
    document.addEventListener('livewire:navigated', initialize);
}());
