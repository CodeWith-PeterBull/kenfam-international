(function () {
    'use strict';

    function createIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({ attrs: { 'stroke-width': 1.8 } });
        }
    }

    function initializeHeader() {
        const header = document.querySelector('[data-travel-header]');
        if (!header || header.dataset.scrollReady === 'true') return;

        header.dataset.scrollReady = 'true';
        const update = () => header.classList.toggle('is-scrolled', window.scrollY > 12);
        window.addEventListener('scroll', update, { passive: true });
        update();
    }

    function initializeBackToTop() {
        const button = document.querySelector('[data-travel-back-to-top]');
        if (!button || button.dataset.initialized === 'true') return;

        button.dataset.initialized = 'true';
        const update = () => button.classList.toggle('visible', window.scrollY > 600);
        window.addEventListener('scroll', update, { passive: true });
        button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        update();
    }

    function initializeMobileMenuLinks() {
        const menu = document.querySelector('#travelMobileMenu');
        if (!menu || menu.dataset.linksReady === 'true') return;

        menu.dataset.linksReady = 'true';
        menu.querySelectorAll('a[href*="#"]').forEach((link) => {
            link.addEventListener('click', () => {
                window.bootstrap?.Offcanvas.getInstance(menu)?.hide();
            });
        });
    }

    function initialize() {
        createIcons();
        initializeHeader();
        initializeBackToTop();
        initializeMobileMenuLinks();
        document.querySelectorAll('[data-current-year]').forEach((element) => {
            element.textContent = String(new Date().getFullYear());
        });
    }

    document.addEventListener('DOMContentLoaded', initialize);
    document.addEventListener('livewire:init', () => {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;
        window.Livewire.hook('morph.updated', createIcons);
    });
    document.addEventListener('livewire:navigated', initialize);
}());
