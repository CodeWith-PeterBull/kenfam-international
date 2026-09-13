/**
 * Product image gallery: a Swiper slider synced to thumbnail tabs, with a
 * theme-aware full-screen lightbox (arrows, dots, keyboard, pinch/zoom).
 *
 * Loaded on demand from storefront.js only when a product gallery is present,
 * so Swiper is code-split into its own chunk instead of every storefront page.
 */
import Swiper from 'swiper';
import { A11y, Keyboard, Navigation, Pagination, Zoom } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import 'swiper/css/zoom';

const CHEVRON_LEFT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>';
const CHEVRON_RIGHT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
const CLOSE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

function escapeAttribute(value) {
    return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export function initProductGallery(root) {
    if (!root || root.dataset.galleryReady === 'true') return;
    root.dataset.galleryReady = 'true';

    const mainElement = root.querySelector('.commerce-gallery-swiper');
    if (!mainElement) return;

    const images = [...mainElement.querySelectorAll('.swiper-slide img')].map((image) => ({
        src: image.currentSrc || image.src,
        alt: image.getAttribute('alt') || '',
    }));
    const multiple = images.length > 1;

    const mainSwiper = new Swiper(mainElement, {
        modules: [Navigation, Pagination, Keyboard, A11y],
        slidesPerView: 1,
        speed: 320,
        keyboard: { enabled: true, onlyInViewport: true },
        a11y: { enabled: true },
        navigation: multiple ? {
            prevEl: root.querySelector('[data-gallery-prev]'),
            nextEl: root.querySelector('[data-gallery-next]'),
        } : false,
        pagination: multiple ? {
            el: root.querySelector('[data-gallery-pagination]'),
            clickable: true,
            dynamicBullets: true,
        } : false,
    });

    // Thumbnail tabs stay a plain vertical list; sync them to the slider by hand.
    const thumbnails = [...root.querySelectorAll('[data-gallery-thumb]')];
    const setActiveThumbnail = (index) => {
        thumbnails.forEach((thumbnail, position) => {
            const active = position === index;
            thumbnail.classList.toggle('is-active', active);
            thumbnail.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) thumbnail.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        });
    };
    thumbnails.forEach((thumbnail, position) => {
        thumbnail.addEventListener('click', () => mainSwiper.slideTo(position));
    });
    mainSwiper.on('slideChange', () => setActiveThumbnail(mainSwiper.activeIndex));
    setActiveThumbnail(0);

    // Full-screen lightbox, built once on first expand.
    let lightbox = null;
    let lightboxSwiper = null;
    let restoreFocusTo = null;

    const onKeydown = (event) => {
        if (event.key === 'Escape') closeLightbox();
    };

    const buildLightbox = () => {
        lightbox = document.createElement('div');
        lightbox.className = 'commerce-gallery-lightbox';
        lightbox.setAttribute('data-gallery-lightbox', '');
        lightbox.hidden = true;
        lightbox.innerHTML = `
            <div class="commerce-gallery-lightbox__backdrop" data-gallery-lightbox-close></div>
            <div class="commerce-gallery-lightbox__dialog" role="dialog" aria-modal="true" aria-label="Product image viewer">
                <button type="button" class="commerce-gallery-lightbox__close" data-gallery-lightbox-close aria-label="Close full screen">${CLOSE_ICON}</button>
                <div class="swiper commerce-gallery-lightbox__swiper">
                    <div class="swiper-wrapper">
                        ${images.map((image) => `
                            <div class="swiper-slide">
                                <div class="swiper-zoom-container">
                                    <img src="${escapeAttribute(image.src)}" alt="${escapeAttribute(image.alt)}">
                                </div>
                            </div>`).join('')}
                    </div>
                    ${multiple ? '<div class="commerce-gallery-lightbox__pagination"></div>' : ''}
                </div>
                ${multiple ? `
                    <button type="button" class="commerce-gallery-lightbox__nav commerce-gallery-lightbox__nav--prev" aria-label="Previous image">${CHEVRON_LEFT}</button>
                    <button type="button" class="commerce-gallery-lightbox__nav commerce-gallery-lightbox__nav--next" aria-label="Next image">${CHEVRON_RIGHT}</button>` : ''}
            </div>`;
        document.body.appendChild(lightbox);

        lightbox.querySelectorAll('[data-gallery-lightbox-close]').forEach((element) => {
            element.addEventListener('click', closeLightbox);
        });

        lightboxSwiper = new Swiper(lightbox.querySelector('.commerce-gallery-lightbox__swiper'), {
            modules: [Navigation, Pagination, Keyboard, A11y, Zoom],
            slidesPerView: 1,
            speed: 320,
            zoom: { maxRatio: 3 },
            keyboard: { enabled: true },
            a11y: { enabled: true },
            navigation: multiple ? {
                prevEl: lightbox.querySelector('.commerce-gallery-lightbox__nav--prev'),
                nextEl: lightbox.querySelector('.commerce-gallery-lightbox__nav--next'),
            } : false,
            pagination: multiple ? {
                el: lightbox.querySelector('.commerce-gallery-lightbox__pagination'),
                clickable: true,
                dynamicBullets: true,
            } : false,
        });

        lightboxSwiper.on('slideChange', () => mainSwiper.slideTo(lightboxSwiper.activeIndex, 0));
    };

    const openLightbox = () => {
        if (!lightbox) buildLightbox();
        restoreFocusTo = document.activeElement;
        lightboxSwiper.slideTo(mainSwiper.activeIndex, 0);
        lightbox.hidden = false;
        requestAnimationFrame(() => lightbox.classList.add('is-open'));
        document.body.classList.add('commerce-scroll-lock');
        document.addEventListener('keydown', onKeydown);
        lightbox.querySelector('.commerce-gallery-lightbox__close')?.focus();
        lightboxSwiper.update();
    };

    function closeLightbox() {
        if (!lightbox || lightbox.hidden) return;
        lightbox.classList.remove('is-open');
        document.body.classList.remove('commerce-scroll-lock');
        document.removeEventListener('keydown', onKeydown);
        window.setTimeout(() => {
            if (!lightbox.classList.contains('is-open')) lightbox.hidden = true;
        }, 260);
        if (restoreFocusTo && typeof restoreFocusTo.focus === 'function') restoreFocusTo.focus();
    }

    root.querySelector('[data-gallery-expand]')?.addEventListener('click', openLightbox);
}
